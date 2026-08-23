<?php

namespace Tests\Feature\Privacy;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDataSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finds_the_data_subject_by_email(): void
    {
        Usuario::factory()->create(['nome' => 'Maria Cliente', 'email' => 'maria@example.com']);

        $this->comoAdmin()
            ->getJson('/api/v1/admin/privacy/subject?email=MARIA@example.com')
            ->assertOk()
            ->assertJsonPath('data.nome', 'Maria Cliente')
            ->assertJsonPath('data.email', 'maria@example.com');
    }

    public function test_admin_export_returns_the_subject_data(): void
    {
        Usuario::factory()->create(['email' => 'maria@example.com', 'telefone' => '51999990000']);

        $this->comoAdmin()
            ->getJson('/api/v1/admin/privacy/subject/export?email=maria@example.com')
            ->assertOk()
            ->assertJsonPath('data.titular.email', 'maria@example.com')
            ->assertJsonPath('data.dados.conta.telefone', '51999990000');
    }

    public function test_admin_deletes_the_subject_account(): void
    {
        Storage::fake('s3');
        $usuario = Usuario::factory()->create(['email' => 'maria@example.com']);

        $this->comoAdmin()
            ->deleteJson('/api/v1/admin/privacy/subject?email=maria@example.com')
            ->assertOk();

        $usuario->refresh();
        $this->assertSame(StatusConta::Excluida, $usuario->status);
        $this->assertSame('Conta removida', $usuario->nome);
    }

    public function test_admin_deletion_respects_open_service(): void
    {
        $servico = Servico::factory()->create(['status' => StatusServico::EmAndamento]);
        $cliente = Usuario::query()->findOrFail($servico->clienteId());

        $this->comoAdmin()
            ->deleteJson('/api/v1/admin/privacy/subject?email='.$cliente->email)
            ->assertStatus(409)
            ->assertJsonPath('code', 'SERVICO_EM_ANDAMENTO');
    }

    public function test_unknown_email_returns_not_found(): void
    {
        $this->comoAdmin()
            ->getJson('/api/v1/admin/privacy/subject?email=ninguem@example.com')
            ->assertNotFound();
    }

    /**
     * Dado de titular é o dado mais sensível do painel: a rota não pode
     * responder para conta comum, só para admin.
     */
    public function test_regular_user_cannot_reach_the_admin_routes(): void
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/privacy/subject?email='.$usuario->email)
            ->assertForbidden();

        // Sem token o bloco admin responde 403, não 401: a checagem de
        // permissão barra antes, e a rota não revela sequer se o e-mail existe.
        $this->getJson('/api/v1/admin/privacy/subject?email=x@example.com')
            ->assertForbidden();
    }

    private function comoAdmin(): self
    {
        $admin = Usuario::factory()->admin()->create();

        return $this->withToken($admin->createToken('auth')->plainTextToken);
    }
}
