<?php

namespace Tests\Feature\Payments;

use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\ReleaseApprovedPayments;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Payments\TipoPaymentEvent;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseApprovedPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_repassa_automaticamente_pagamento_de_servico_aprovado(): void
    {
        // Achado de auditoria (2026-08-20): antes deste job, REPASSADO só
        // saía por POST /payments/{id}/release manual - nenhum profissional
        // recebia pagamento sem um Admin clicar, mesmo dentro da janela que
        // adr/ADR-004-prazo-aceite-automatico.md sempre descreveu como
        // automática ("aprovado automaticamente -> captura + repasse").
        $servico = $this->servicoAprovado();
        $authorization = PaymentAuthorization::factory()->capturado()->create([
            'servico_id' => $servico->id,
            'wallet_id' => 'wal_profissional',
        ]);

        $processados = app(ReleaseApprovedPayments::class)();

        $this->assertSame(1, $processados);
        $this->assertTrue($authorization->fresh()->hasRepasse());

        $split = $authorization->captureEvent()->split;
        $this->assertSame(
            [['walletId' => 'wal_profissional', 'amount' => $split->valor_profissional]],
            app(FakePaymentGateway::class)->transfers,
        );
    }

    public function test_nao_repassa_de_novo_se_ja_repassado(): void
    {
        $servico = $this->servicoAprovado();
        $authorization = PaymentAuthorization::factory()->capturado()->create([
            'servico_id' => $servico->id,
            'wallet_id' => 'wal_profissional',
        ]);

        app(ReleaseApprovedPayments::class)();
        $processados = app(ReleaseApprovedPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertSame(
            1,
            $authorization->events()->where('tipo', TipoPaymentEvent::Repassado)->count(),
        );
        $this->assertCount(1, app(FakePaymentGateway::class)->transfers);
    }

    public function test_nao_repassa_com_disputa_aberta(): void
    {
        // INV-045: disputa aberta bloqueia repasse, mesmo automático.
        $servico = $this->servicoAprovado();
        $authorization = PaymentAuthorization::factory()->capturado()->create([
            'servico_id' => $servico->id,
            'wallet_id' => 'wal_profissional',
        ]);

        PaymentDispute::query()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::Chargeback,
            'status' => StatusPaymentDispute::Aberta,
            'motivo' => 'Chargeback reportado pelo Asaas.',
            'aberta_em' => now(),
        ]);

        $processados = app(ReleaseApprovedPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertFalse($authorization->fresh()->hasRepasse());
        $this->assertCount(0, app(FakePaymentGateway::class)->transfers);
    }

    public function test_nao_repassa_pagamento_ainda_nao_capturado(): void
    {
        $servico = $this->servicoAprovado();
        // Cartão aprovado mas CapturePaymentJob (fila) ainda não rodou -
        // autorização segue AUTORIZADO até a captura acontecer de fato.
        $authorization = PaymentAuthorization::factory()->create([
            'servico_id' => $servico->id,
            'status' => StatusPaymentAuthorization::Autorizado,
            'wallet_id' => 'wal_profissional',
        ]);

        $processados = app(ReleaseApprovedPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertSame(StatusPaymentAuthorization::Autorizado, $authorization->fresh()->status);
    }

    public function test_nao_repassa_multa_de_cenario_b_automaticamente(): void
    {
        // Escopo deliberado (ver docblock de ReleaseApprovedPayments): o
        // split de uma captura de multa (Cenário B) é calculado sobre o
        // valor cheio da proposta, não sobre a multa retida - repassar
        // automaticamente aqui pagaria o profissional pelo serviço
        // inteiro. Esse caminho continua exigindo liberação manual.
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->aceita()->create(['solicitacao_id' => $solicitacao->id]);
        $servico = Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Cancelado,
        ]);
        $authorization = PaymentAuthorization::factory()->capturado()->create([
            'servico_id' => $servico->id,
            'metodo' => MetodoPagamento::Cartao,
            'wallet_id' => 'wal_profissional',
        ]);

        $processados = app(ReleaseApprovedPayments::class)();

        $this->assertSame(0, $processados);
        $this->assertFalse($authorization->fresh()->hasRepasse());
        $this->assertCount(0, app(FakePaymentGateway::class)->transfers);
    }

    private function servicoAprovado(): Servico
    {
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->aceita()->create(['solicitacao_id' => $solicitacao->id]);

        return Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::Aprovado,
        ]);
    }
}
