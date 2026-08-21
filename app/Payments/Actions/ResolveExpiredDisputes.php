<?php

namespace App\Payments\Actions;

use App\Admin\Configuracao;
use App\Payments\PaymentDispute;
use App\Payments\ResultadoPaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Services\Events\ServiceApproved;
use App\Services\Servico;
use App\Services\StatusServico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Timeout automático de disputa (`foundation/03-cancellation-rules.md`,
 * "Prazo e timeout"): `DISPUTE_MEDIATION_DAYS` (7 dias corridos) a partir
 * de `PaymentDispute.aberta_em` sem decisão do Admin resolve sozinha -
 * `CONTESTACAO_CONCLUSAO` vira `APROVADO` (Serviço `Aprovado`, mesma
 * captura integral do aceite automático de B002), `CANCELAMENTO_EXECUCAO`
 * vira `CANCELADO` (Serviço `Cancelado`, libera autorização integral).
 *
 * Antes deste job só existia `ResolveDispute`, endpoint manual do Admin -
 * uma disputa sem decisão humana ficava `ABERTA` para sempre, travando o
 * Serviço em `Em Contestação` além do prazo que a própria documentação
 * sempre chamou de automático (mesmo achado de auditoria de
 * `ReleaseApprovedPayments`, 2026-08-20).
 *
 * Não grava `Auditoria`: essa tabela exige um `usuario_id` humano
 * (`restrictOnDelete` em `usuarios`), e não há admin por trás de um
 * timeout - mesmo padrão de `ApproveExpiredServices`, que também não
 * audita a aprovação automática por hora expirada. O motivo fica em
 * `PaymentDispute.justificativa` (`resolvida_por_id` permanece null).
 *
 * `CHARGEBACK` fica fora do escopo: não tem timeout definido em
 * `03-cancellation-rules.md`, e `ApplyDisputeOutcome` recusaria de
 * qualquer forma.
 */
class ResolveExpiredDisputes
{
    public function __construct(
        private readonly ApplyDisputeOutcome $applyOutcome,
    ) {}

    public function __invoke(): int
    {
        $limite = now()->subDays(Configuracao::inteiro('DISPUTE_MEDIATION_DAYS'));

        $ids = PaymentDispute::query()
            ->where('status', StatusPaymentDispute::Aberta)
            ->whereIn('tipo', [TipoPaymentDispute::ContestacaoConclusao, TipoPaymentDispute::CancelamentoExecucao])
            ->where('aberta_em', '<=', $limite)
            ->pluck('id');

        $resolvidas = 0;

        foreach ($ids as $id) {
            if ($this->process((string) $id)) {
                $resolvidas++;
            }
        }

        return $resolvidas;
    }

    private function process(string $disputeId): bool
    {
        return DB::transaction(function () use ($disputeId): bool {
            $dispute = PaymentDispute::query()->lockForUpdate()->find($disputeId);

            if ($dispute === null || $dispute->status !== StatusPaymentDispute::Aberta) {
                return false;
            }

            $servico = Servico::query()->lockForUpdate()->find($dispute->servico_id);

            if ($servico === null || $servico->status !== StatusServico::EmContestacao) {
                return false;
            }

            $resultado = match ($dispute->tipo) {
                TipoPaymentDispute::ContestacaoConclusao => ResultadoPaymentDispute::Aprovado,
                TipoPaymentDispute::CancelamentoExecucao => ResultadoPaymentDispute::Cancelado,
                TipoPaymentDispute::Chargeback => null,
            };

            if ($resultado === null) {
                return false;
            }

            ($this->applyOutcome)($servico, $dispute->tipo, $resultado);

            $dispute->status = StatusPaymentDispute::Resolvida;
            $dispute->resultado = $resultado;
            $dispute->justificativa = 'TIMEOUT_AUTOMATICO';
            $dispute->resolvida_em = CarbonImmutable::now();
            $dispute->save();

            if ($servico->fresh()->status === StatusServico::Aprovado) {
                ServiceApproved::dispatch($servico->fresh(), automatico: true);
            }

            return true;
        });
    }
}
