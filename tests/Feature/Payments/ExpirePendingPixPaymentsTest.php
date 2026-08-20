<?php

namespace Tests\Feature\Payments;

use App\Payments\ExpirePendingPixPayments;
use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\PaymentAuthorization;
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
