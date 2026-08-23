<?php

namespace Tests\Feature\Privacy;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Professionals\DocumentoProfissional;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataSubjectRightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_the_data_of_the_authenticated_user(): void
    {
        $usuario = Usuario::factory()->create([
            'nome' => 'Maria Cliente',
            'email' => 'maria@example.com',
            'telefone' => '51999990000',
        ]);
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/privacy/data-export')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conta.nome', 'Maria Cliente')
            ->assertJsonPath('data.conta.email', 'maria@example.com')
            ->assertJsonPath('data.conta.telefone', '51999990000')
            ->assertJsonStructure(['data' => [
                'gerado_em', 'conta', 'imoveis', 'solicitacoes',
                'propostas_enviadas', 'servicos', 'mensagens_enviadas',
            ]]);
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/privacy/data-export')->assertUnauthorized();
    }

    /**
     * Exportar dado de titular não pode virar porta de saída para o dado de
     * quem negociou com ele: a mensagem que o outro lado escreveu fica fora.
     */
    public function test_export_does_not_leak_messages_written_by_the_other_party(): void
    {
        $servico = Servico::factory()->aprovado()->create();
        $cliente = Usuario::query()->findOrFail($servico->clienteId());

        DB::table('mensagens')->insert([
            ['id' => (string) Str::uuid(), 'servico_id' => $servico->id, 'remetente_id' => $cliente->id, 'texto' => 'mensagem do cliente', 'enviado_em' => now()],
            ['id' => (string) Str::uuid(), 'servico_id' => $servico->id, 'remetente_id' => $servico->profissionalId(), 'texto' => 'mensagem do profissional', 'enviado_em' => now()],
        ]);

        $token = $cliente->createToken('auth')->plainTextToken;

        $resposta = $this->withToken($token)->getJson('/api/v1/privacy/data-export')->assertOk();

        $resposta->assertSee('mensagem do cliente');
        $resposta->assertDontSee('mensagem do profissional');
    }

    public function test_account_deletion_anonymizes_the_user_and_keeps_the_transaction(): void
    {
        Storage::fake('s3');

        $servico = Servico::factory()->aprovado()->create();
        $cliente = Usuario::query()->findOrFail($servico->clienteId());
        $token = $cliente->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/privacy/account')
            ->assertOk()
            ->assertJsonPath('success', true);

        $cliente->refresh();

        $this->assertSame('Conta removida', $cliente->nome);
        $this->assertNull($cliente->telefone);
        $this->assertNull($cliente->foto);
        $this->assertSame(StatusConta::Excluida, $cliente->status);
        $this->assertStringEndsWith('@resolveplus.invalid', $cliente->email);

        // O serviço continua de pé: é registro de uma transação entre duas
        // pessoas, e o outro lado ainda tem direito sobre ele.
        $this->assertDatabaseHas('servicos', ['id' => $servico->id]);

        // Sessão encerrada junto: o token de quem pediu exclusão não segue valendo.
        $this->assertSame(0, $cliente->tokens()->count());
    }

    public function test_account_deletion_erases_professional_documents_and_files(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('documents/rg.jpg', 'conteudo');

        $profissional = Usuario::factory()->profissional()->create();
        DocumentoProfissional::factory()->create([
            'profissional_id' => $profissional->id,
            'arquivo' => 'documents/rg.jpg',
        ]);

        $token = $profissional->createToken('auth')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/privacy/account')->assertOk();

        Storage::disk('s3')->assertMissing('documents/rg.jpg');
        $this->assertDatabaseMissing('documentos_profissional', ['profissional_id' => $profissional->id]);
    }

    public function test_account_deletion_is_refused_while_a_service_is_open(): void
    {
        $servico = Servico::factory()->create(['status' => StatusServico::EmAndamento]);
        $cliente = Usuario::query()->findOrFail($servico->clienteId());
        $token = $cliente->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/privacy/account')
            ->assertStatus(409)
            ->assertJsonPath('code', 'SERVICO_EM_ANDAMENTO');

        $cliente->refresh();
        $this->assertNotSame(StatusConta::Excluida, $cliente->status);
    }

    public function test_purge_command_removes_expired_transient_data(): void
    {
        $usuario = Usuario::factory()->create();

        DB::table('links_magicos')->insert([
            'id' => (string) Str::uuid(),
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', 'antigo'),
            'expires_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
        ]);
        DB::table('links_magicos')->insert([
            'id' => (string) Str::uuid(),
            'usuario_id' => $usuario->id,
            'token_hash' => hash('sha256', 'valido'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ]);

        $this->artisan('privacy:purge')->assertExitCode(0);

        $this->assertSame(1, DB::table('links_magicos')->count());
    }
}
