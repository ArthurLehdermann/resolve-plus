<?php

namespace Tests\Feature\Payments;

use App\Payments\Actions\ResolveExpiredDisputes;
use App\Payments\Jobs\CapturePaymentJob;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResolveExpiredDisputesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_contestacao_conclusao_expirada_aprova_servico_e_captura(): void
    {
        // Achado de auditoria (2026-08-20): foundation/03-cancellation-rules.md
        // sempre descreveu o timeout de 7 dias (DISPUTE_MEDIATION_DAYS) como
        // resolução automática, mas só existia ResolveDispute (endpoint
        // manual do Admin) - uma disputa sem decisão humana ficava ABERTA
        // para sempre.
        Queue::fake();

        $servico = $this->servicoEmContestacao();
        $dispute = PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(8),
        ]);

        $resolvidas = app(ResolveExpiredDisputes::class)();

        $this->assertSame(1, $resolvidas);
        $this->assertSame(StatusServico::Aprovado, $servico->fresh()->status);

        $dispute = $dispute->fresh();
        $this->assertSame(StatusPaymentDispute::Resolvida, $dispute->status);
        $this->assertSame('TIMEOUT_AUTOMATICO', $dispute->justificativa);
        $this->assertNull($dispute->resolvida_por_id);

        Queue::assertPushed(CapturePaymentJob::class, fn (CapturePaymentJob $job): bool => $job->servicoId === $servico->id);
    }

    public function test_resolve_cancelamento_execucao_expirada_cancela_servico_e_libera_autorizacao(): void
    {
        $servico = $this->servicoEmContestacao();
        $authorization = PaymentAuthorization::factory()->create([
            'servico_id' => $servico->id,
            'status' => StatusPaymentAuthorization::Autorizado,
        ]);
        PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::CancelamentoExecucao,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(8),
        ]);

        $resolvidas = app(ResolveExpiredDisputes::class)();

        $this->assertSame(1, $resolvidas);
        $this->assertSame(StatusServico::Cancelado, $servico->fresh()->status);
        $this->assertSame(StatusPaymentAuthorization::Cancelado, $authorization->fresh()->status);
    }

    public function test_nao_resolve_disputa_dentro_do_prazo(): void
    {
        $servico = $this->servicoEmContestacao();
        $dispute = PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(2),
        ]);

        $resolvidas = app(ResolveExpiredDisputes::class)();

        $this->assertSame(0, $resolvidas);
        $this->assertSame(StatusPaymentDispute::Aberta, $dispute->fresh()->status);
        $this->assertSame(StatusServico::EmContestacao, $servico->fresh()->status);
    }

    public function test_nao_resolve_chargeback_automaticamente(): void
    {
        $servico = $this->servicoEmContestacao();
        $dispute = PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::Chargeback,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(8),
        ]);

        $resolvidas = app(ResolveExpiredDisputes::class)();

        $this->assertSame(0, $resolvidas);
        $this->assertSame(StatusPaymentDispute::Aberta, $dispute->fresh()->status);
    }

    public function test_nao_reprocessa_disputa_ja_resolvida(): void
    {
        $servico = $this->servicoEmContestacao();
        PaymentDispute::factory()->create([
            'servico_id' => $servico->id,
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now()->subDays(8),
        ]);

        app(ResolveExpiredDisputes::class)();
        $resolvidas = app(ResolveExpiredDisputes::class)();

        $this->assertSame(0, $resolvidas);
    }

    private function servicoEmContestacao(): Servico
    {
        $solicitacao = Solicitacao::factory()->contratada()->create();
        $proposta = Proposta::factory()->aceita()->create(['solicitacao_id' => $solicitacao->id]);

        return Servico::factory()->create([
            'proposta_id' => $proposta->id,
            'status' => StatusServico::EmContestacao,
        ]);
    }
}
