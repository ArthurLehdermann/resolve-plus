<?php

namespace Tests\Feature\Payments;

use App\Payments\ExpirePendingPixPayments;
use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\Gateway\GatewayCharge;
use App\Payments\Gateway\GatewayException;
use App\Payments\Gateway\PaymentGateway;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentSplit;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpirePendingPixPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expira_pix_pendente_antigo_e_libera_solicitacao_para_novas_propostas(): void
    {
        $this->freezeTime();

        [$solicitacao, $proposta, $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(25),
        ]);

        $processados = app(ExpirePendingPixPayments::class)();

        $this->assertSame(1, $processados);

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Expirado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Expirado));

        $this->assertSame(StatusServico::Cancelado, $servico->fresh()->status);
        $this->assertSame(StatusProposta::Recusada, $proposta->fresh()->status);
        $this->assertSame(StatusSolicitacao::RecebendoPropostas, $solicitacao->fresh()->status);

        $gateway = app(FakePaymentGateway::class);
        $this->assertContains($authorization->gateway_payment_id, $gateway->cancels);
    }

    public function test_nao_expira_pix_pendente_dentro_da_janela(): void
    {
        $this->freezeTime();

        [$solicitacao, $proposta, $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(1),
        ]);

        $processados = app(ExpirePendingPixPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertSame(StatusPaymentAuthorization::Pendente, $authorization->fresh()->status);
        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
        $this->assertSame(StatusSolicitacao::Contratada, $solicitacao->fresh()->status);
    }

    public function test_nao_toca_pix_ja_confirmado(): void
    {
        $this->freezeTime();

        [, , $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixCapturado()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(25),
        ]);

        $processados = app(ExpirePendingPixPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->fresh()->status);
        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
    }

    public function test_confirma_em_vez_de_expirar_quando_gateway_ja_diz_confirmado(): void
    {
        $this->freezeTime();

        [$solicitacao, $proposta, $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(25),
            'gateway_payment_id' => 'pay_ja_pago',
        ]);

        // O Asaas já tem o pagamento como recebido, mas o webhook ainda não
        // chegou - a consulta ativa antes de expirar (N9) descobre isso.
        app(FakePaymentGateway::class)->statuses['pay_ja_pago'] = 'RECEIVED';

        app(ExpirePendingPixPayments::class)();

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));
        $this->assertFalse($authorization->hasEvent(TipoPaymentEvent::Expirado));

        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
        $this->assertSame(StatusProposta::Aceita, $proposta->fresh()->status);
        $this->assertSame(StatusSolicitacao::Contratada, $solicitacao->fresh()->status);

        $gateway = app(FakePaymentGateway::class);
        $this->assertNotContains('pay_ja_pago', $gateway->cancels);

        // Achado de auditoria (2026-08-20): esta reconciliação gravava o
        // evento CAPTURADO mas nunca calculava o PaymentSplit (INV-044),
        // deixando o repasse futuro sem transferência real.
        $split = PaymentSplit::query()
            ->where('payment_event_id', $authorization->captureEvent()->id)
            ->first();
        $this->assertNotNull($split);
        $this->assertSame($authorization->valor, $split->valor_profissional + $split->valor_plataforma);
    }

    public function test_aborta_expiracao_quando_cancel_falha_no_gateway(): void
    {
        $this->freezeTime();

        // O motivo mais provável do cancel falhar é o Pix já ter sido pago
        // (Asaas não remove cobrança recebida) - falha aqui não pode virar
        // warning-e-segue (N9), senão a autorização é marcada EXPIRADO por
        // cima de dinheiro que já chegou.
        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function capture(string $gatewayPaymentId, int $amountCents, array $splits = []): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function chargePix(string $customerId, int $amountCents): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function find(string $gatewayPaymentId): GatewayCharge
            {
                return new GatewayCharge(id: $gatewayPaymentId, status: 'PENDING');
            }

            public function cancel(string $gatewayPaymentId): void
            {
                throw new GatewayException('cobrança já recebida, não pode ser cancelada');
            }

            public function transfer(string $walletId, int $amountCents): string
            {
                throw new GatewayException('não usado neste teste');
            }
        });

        [$solicitacao, $proposta, $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(25),
        ]);

        app(ExpirePendingPixPayments::class)();

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Pendente, $authorization->status);
        $this->assertFalse($authorization->hasEvent(TipoPaymentEvent::Expirado));

        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
        $this->assertSame(StatusProposta::Aceita, $proposta->fresh()->status);
        $this->assertSame(StatusSolicitacao::Contratada, $solicitacao->fresh()->status);
    }

    public function test_aborta_expiracao_quando_consulta_ao_gateway_falha(): void
    {
        $this->freezeTime();

        $this->app->instance(PaymentGateway::class, new class implements PaymentGateway
        {
            public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function capture(string $gatewayPaymentId, int $amountCents, array $splits = []): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function chargePix(string $customerId, int $amountCents): GatewayCharge
            {
                throw new GatewayException('não usado neste teste');
            }

            public function find(string $gatewayPaymentId): GatewayCharge
            {
                throw new GatewayException('timeout consultando o Asaas');
            }

            public function cancel(string $gatewayPaymentId): void
            {
                throw new GatewayException('não usado neste teste');
            }

            public function transfer(string $walletId, int $amountCents): string
            {
                throw new GatewayException('não usado neste teste');
            }
        });

        [$solicitacao, $proposta, $servico] = $this->contexto();

        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'servico_id' => $servico->id,
            'criado_em' => now()->subHours(25),
        ]);

        app(ExpirePendingPixPayments::class)();

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Pendente, $authorization->status);
        $this->assertSame(StatusServico::Agendado, $servico->fresh()->status);
        $this->assertSame(StatusProposta::Aceita, $proposta->fresh()->status);
        $this->assertSame(StatusSolicitacao::Contratada, $solicitacao->fresh()->status);
    }

    /**
     * @return array{0: Solicitacao, 1: Proposta, 2: Servico}
     */
    private function contexto(): array
    {
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->aceita()->create([
            'solicitacao_id' => $solicitacao->id,
        ]);
        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Agendado,
        ]);

        return [$solicitacao, $proposta, $servico];
    }
}
