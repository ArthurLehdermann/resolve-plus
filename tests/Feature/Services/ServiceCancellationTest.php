<?php

namespace Tests\Feature\Services;

use App\Admin\Configuracao;
use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Payments\TipoPaymentEvent;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cenario_a_cliente_cancela_solicitacao_antes_de_proposta_aceita(): void
    {
        $cliente = Usuario::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'cliente_id' => $cliente->id,
            'status' => StatusSolicitacao::RecebendoPropostas,
        ]);

        $solicitacao->status = StatusSolicitacao::Cancelada;
        $solicitacao->save();

        $this->assertSame(StatusSolicitacao::Cancelada, $solicitacao->fresh()->status);
        $this->assertSame(0, PaymentAuthorization::query()->count());
    }

    public function test_cenario_b_cancela_agendado_com_multa_e_captura_parcial(): void
    {
        $this->freezeTime();
        Configuracao::query()->updateOrInsert(['chave' => 'CANCELLATION_PENALTY_TIER1_HOURS'], ['valor' => '48']);
        Configuracao::query()->updateOrInsert(['chave' => 'CANCELLATION_PENALTY_TIER1_PERCENT'], ['valor' => '10']);

        [$cliente, , $servico] = $this->contexto(StatusServico::Agendado);

        $authorization = PaymentAuthorization::factory()->create([
            'servico_id' => $servico->id,
            'valor' => 35_000,
            'wallet_id' => 'wal_teste',
        ]);

        $key = (string) Str::uuid();

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.servico.status', 'CANCELADO')
            ->assertJsonPath('data.multa.percentual', 10)
            ->assertJsonPath('data.multa.valor_centavos', 3500);

        $this->assertSame(StatusServico::Cancelado, $servico->fresh()->status);
        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));

        $capturePayload = $authorization->captureEvent()?->payload ?? [];
        $this->assertSame('CANCELAMENTO_MULTA', $capturePayload['motivo'] ?? null);

        $gateway = app(FakePaymentGateway::class);
        $this->assertNotEmpty($gateway->captures);
        $this->assertContains($authorization->gateway_payment_id, $gateway->cancels);
    }

    public function test_cenario_b_multa_zero_cancela_autorizacao_sem_captura(): void
    {
        $cliente = Usuario::factory()->create();
        $profissional = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
        $solicitacao = Solicitacao::factory()->contratada()->create(['cliente_id' => $cliente->id]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
            'valor' => 5,
            'prazo_dias' => 3,
        ]);
        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Agendado,
            'created_at' => now(),
        ]);

        $authorization = PaymentAuthorization::factory()->create([
            'servico_id' => $servico->id,
            'valor' => 5,
        ]);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.multa.valor_centavos', 0);

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Cancelado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Cancelado));
    }

    public function test_cenario_c_em_andamento_abre_disputa_em_vez_de_cancelar(): void
    {
        [$cliente, $profissional, $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel", [
                'motivo' => 'Impossível continuar.',
            ])
            ->assertOk()
            ->assertJsonPath('data.servico.status', 'EM_CONTESTACAO')
            ->assertJsonPath('data.dispute.tipo', 'CANCELAMENTO_EXECUCAO');

        $this->assertSame(StatusServico::EmContestacao, $servico->fresh()->status);
        $this->assertNotSame(StatusServico::Cancelado, $servico->fresh()->status);

        $dispute = PaymentDispute::query()->first();
        $this->assertNotNull($dispute);
        $this->assertSame(TipoPaymentDispute::CancelamentoExecucao, $dispute->tipo);
        $this->assertSame(StatusPaymentDispute::Aberta, $dispute->status);

        $this->asUser($profissional)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.servico.status', 'EM_CONTESTACAO');

        $this->assertSame(1, PaymentDispute::query()->count());
    }

    public function test_cenario_c_profissional_tambem_pode_abrir_disputa(): void
    {
        [, $profissional, $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($profissional)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel", ['motivo' => 'Cliente ausente'])
            ->assertOk()
            ->assertJsonPath('data.dispute.tipo', 'CANCELAMENTO_EXECUCAO');
    }

    public function test_admin_resolve_disputa_cancelamento_execucao_aprovado_retoma_andamento(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::EmAndamento);
        $admin = Usuario::factory()->create(['tipo' => TipoUsuario::Admin]);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk();

        $dispute = PaymentDispute::query()->firstOrFail();

        $this->asUser($admin)
            ->putJson("/api/v1/disputes/{$dispute->id}/resolve", [
                'resultado' => 'APROVADO',
                'justificativa' => 'Pedido oportunista; execução deve continuar.',
            ])
            ->assertOk()
            ->assertJsonPath('data.resultado', 'APROVADO')
            ->assertJsonPath('data.servico_status', 'EM_ANDAMENTO');

        $this->assertSame(StatusServico::EmAndamento, $servico->fresh()->status);
        $this->assertSame(StatusPaymentDispute::Resolvida, $dispute->fresh()->status);
    }

    public function test_admin_resolve_disputa_cancelamento_execucao_cancelado_libera_autorizacao(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::EmAndamento);
        $admin = Usuario::factory()->create(['tipo' => TipoUsuario::Admin]);

        $authorization = PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk();

        $dispute = PaymentDispute::query()->firstOrFail();

        $this->asUser($admin)
            ->putJson("/api/v1/disputes/{$dispute->id}/resolve", [
                'resultado' => 'CANCELADO',
                'justificativa' => 'Motivo razoável para encerrar o serviço.',
            ])
            ->assertOk()
            ->assertJsonPath('data.servico_status', 'CANCELADO');

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Cancelado, $authorization->status);
    }

    public function test_admin_resolve_contestacao_conclusao_aprovado(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);
        $admin = Usuario::factory()->create(['tipo' => TipoUsuario::Admin]);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/contest", ['motivo' => 'Serviço incompleto'])
            ->assertOk();

        $dispute = PaymentDispute::query()->firstOrFail();

        $this->asUser($admin)
            ->putJson("/api/v1/disputes/{$dispute->id}/resolve", [
                'resultado' => 'APROVADO',
                'justificativa' => 'Evidências não sustentam falha grave.',
            ])
            ->assertOk()
            ->assertJsonPath('data.servico_status', 'APROVADO');

        $this->assertSame(StatusServico::Aprovado, $servico->fresh()->status);
    }

    public function test_post_services_disputes_cria_payment_dispute(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/contest", ['motivo' => 'x'])
            ->assertOk();

        $this->assertSame(StatusServico::EmContestacao, $servico->fresh()->status);

        $this->asUser($cliente)
            ->postJson("/api/v1/services/{$servico->id}/disputes", [
                'tipo' => 'CONTESTACAO_CONCLUSAO',
                'motivo' => 'Administrativo',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.tipo', 'CONTESTACAO_CONCLUSAO');
    }

    public function test_post_services_disputes_rejeita_usuario_que_nao_e_participante(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::AguardandoAprovacao);
        $estranho = Usuario::factory()->create();

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/contest", ['motivo' => 'x'])
            ->assertOk();

        $this->asUser($estranho)
            ->postJson("/api/v1/services/{$servico->id}/disputes", [
                'tipo' => 'CONTESTACAO_CONCLUSAO',
                'motivo' => 'Terceiro sem relação com o serviço.',
            ])
            ->assertForbidden();

        $this->assertSame(1, PaymentDispute::query()->count());
    }

    public function test_cancel_em_andamento_rejeita_nao_participante(): void
    {
        [, , $servico] = $this->contexto(StatusServico::EmAndamento);
        $estranho = Usuario::factory()->create();

        $this->asUser($estranho)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel", ['motivo' => 'Não sou parte'])
            ->assertForbidden();

        $this->assertSame(StatusServico::EmAndamento, $servico->fresh()->status);
        $this->assertSame(0, PaymentDispute::query()->count());
    }

    public function test_cancel_agendado_rejeita_nao_cliente(): void
    {
        [, $profissional, $servico] = $this->contexto(StatusServico::Agendado);

        $this->asUser($profissional)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertForbidden();
    }

    public function test_resolve_disputa_exige_admin_e_justificativa(): void
    {
        [$cliente, , $servico] = $this->contexto(StatusServico::EmAndamento);

        $this->asUser($cliente)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/services/{$servico->id}/cancel")
            ->assertOk();

        $dispute = PaymentDispute::query()->firstOrFail();

        $this->asUser($cliente)
            ->putJson("/api/v1/disputes/{$dispute->id}/resolve", [
                'resultado' => 'CANCELADO',
                'justificativa' => 'Justificativa válida porém sem permissão.',
            ])
            ->assertForbidden();

        $admin = Usuario::factory()->create(['tipo' => TipoUsuario::Admin]);

        $this->asUser($admin)
            ->putJson("/api/v1/disputes/{$dispute->id}/resolve", [
                'resultado' => 'CANCELADO',
                'justificativa' => 'ab',
            ])
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Usuario, 1: Usuario, 2: Servico}
     */
    private function contexto(StatusServico $status = StatusServico::Agendado): array
    {
        $cliente = Usuario::factory()->create();
        $profissional = Usuario::factory()->create([
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::Ativa,
        ]);
        $solicitacao = Solicitacao::factory()->contratada()->create([
            'cliente_id' => $cliente->id,
        ]);
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
            'profissional_id' => $profissional->id,
            'valor' => 35_000,
            'prazo_dias' => 3,
        ]);

        $attributes = [
            'proposta_id' => $proposta->id,
            'status' => $status,
            'created_at' => now(),
        ];

        if ($status === StatusServico::EmAndamento) {
            $attributes['inicio'] = now();
        }

        if ($status === StatusServico::AguardandoAprovacao) {
            $attributes['inicio'] = now()->subHour();
            $attributes['fim'] = now();
        }

        $servico = Servico::factory()->create($attributes);

        return [$cliente, $profissional, $servico];
    }

    private function asUser(Usuario $usuario): static
    {
        $this->flushHeaders();
        Auth::forgetGuards();

        return $this->actingAs($usuario, 'sanctum');
    }
}
