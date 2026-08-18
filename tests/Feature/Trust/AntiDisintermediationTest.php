<?php

namespace Tests\Feature\Trust;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\Auditoria;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Services\Mensagem;
use App\Services\Servico;
use App\Services\StatusServico;
use App\Trust\Models\ContactLeakAttempt;
use App\Trust\Models\ContactPenaltyNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AntiDisintermediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_proposal_notes_logs_attempt_and_warns_sender(): void
    {
        $profissional = Usuario::factory()->profissionalAtivo()->create();
        $token = $profissional->createToken('auth')->plainTextToken;
        $solicitacao = Solicitacao::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/requests/'.$solicitacao->id.'/proposals', [
            'price' => 35000,
            'deadline_days' => 2,
            'warranty_days' => 90,
            'notes' => 'Me chama no whats 11 99999-8888 e email teste@exemplo.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warning', 'Detectamos tentativa de compartilhamento de contato. O trecho foi removido para manter a negociação protegida na plataforma.');

        $proposta = Proposta::query()->firstOrFail();
        $this->assertStringContainsString('[contato removido]', (string) $proposta->observacoes);
        $this->assertNotSame($proposta->observacoes_original, $proposta->observacoes);
        $this->assertStringContainsString('teste@exemplo.com', (string) $proposta->observacoes_original);

        $this->assertGreaterThanOrEqual(1, ContactLeakAttempt::query()->count());
    }

    public function test_filters_chat_message_applies_automatic_penalty_and_suspends_on_fifth_attempt(): void
    {
        $profissional = Usuario::factory()->profissionalAtivo()->create();
        $token = $profissional->createToken('auth')->plainTextToken;
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->contratada()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
        ]);
        $servico = Servico::factory()->emAndamento()->create([
            'proposta_id' => $proposta->id,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token)->postJson('/api/v1/services/'.$servico->id.'/messages', [
                'text' => 'Meu contato é 11 98888-7777',
            ])->assertCreated();
        }

        $mensagem = Mensagem::query()->firstOrFail();
        $this->assertStringContainsString('[contato removido]', $mensagem->texto);
        $this->assertNotNull($mensagem->texto_original);

        $profissional->refresh();

        $this->assertSame(StatusConta::Suspensa, $profissional->status);
        $this->assertGreaterThanOrEqual(2, ContactPenaltyNote::query()->count());
        $this->assertTrue(
            Auditoria::query()
                ->where('acao', 'CONTACT_LEAK_AUTO_SUSPEND')
                ->where('id_entidade', $profissional->id)
                ->exists()
        );
    }

    public function test_admin_dashboard_exposes_contact_leak_metrics(): void
    {
        $admin = Usuario::factory()->create([
            'tipo' => TipoUsuario::Admin,
            'status' => StatusConta::Ativa,
        ]);
        $profissional = Usuario::factory()->profissionalAtivo()->create();
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
            'observacoes' => '[contato removido]',
            'observacoes_original' => 'email teste@exemplo.com',
            'status' => StatusProposta::Aceita,
        ]);
        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Aprovado,
        ]);

        ContactLeakAttempt::query()->create([
            'usuario_id' => $profissional->id,
            'origem' => 'PROPOSTA',
            'proposta_id' => $proposta->id,
            'padrao_detectado' => 'EMAIL',
            'texto_original' => 'email teste@exemplo.com',
            'texto_filtrado' => '[contato removido]',
        ]);

        ContactLeakAttempt::query()->create([
            'usuario_id' => $profissional->id,
            'origem' => 'MENSAGEM',
            'servico_id' => $servico->id,
            'padrao_detectado' => 'TELEFONE',
            'texto_original' => '11 99999-0000',
            'texto_filtrado' => '[contato removido]',
        ]);

        $token = $admin->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contact_leak.total_attempts', 2)
            ->assertJsonPath('data.contact_leak.attempt_rate_pre_acceptance', 100)
            ->assertJsonPath('data.contact_leak.attempt_rate_post_acceptance', 100)
            ->assertJsonPath('data.contact_leak.post_attempt_completion_rate', 100);
    }
}
