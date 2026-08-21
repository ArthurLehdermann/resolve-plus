<?php

namespace App\Payments\Actions;

use App\Payments\CancelAuthorizedPayment;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDomainException;
use App\Payments\ResultadoPaymentDispute;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentDispute;
use App\Services\Servico;
use App\Services\StatusServico;

/**
 * Efeito no Serviço e na autorização de cartão pendente de uma disputa
 * resolvida - a mesma tabela de `foundation/03-cancellation-rules.md`
 * ("Resolução de Em Contestação"), disparada tanto pela decisão manual do
 * Admin (`ResolveDispute`) quanto pelo timeout automático de
 * `DISPUTE_MEDIATION_DAYS` (`ResolveExpiredDisputes`). Extraído de
 * `ResolveDispute` para não duplicar o `match` entre os dois caminhos.
 */
class ApplyDisputeOutcome
{
    public function __construct(
        private readonly CancelAuthorizedPayment $cancelAuthorized,
    ) {}

    public function __invoke(Servico $servico, TipoPaymentDispute $tipo, ResultadoPaymentDispute $resultado): void
    {
        match ($tipo) {
            TipoPaymentDispute::ContestacaoConclusao => $this->resolveContestacaoConclusao($servico, $resultado),
            TipoPaymentDispute::CancelamentoExecucao => $this->resolveCancelamentoExecucao($servico, $resultado),
            TipoPaymentDispute::Chargeback => throw new PaymentDomainException(
                'Disputa de chargeback ainda não tem resolução automatizada por este endpoint; trate manualmente.',
            ),
        };
    }

    private function resolveContestacaoConclusao(Servico $servico, ResultadoPaymentDispute $resultado): void
    {
        if ($resultado === ResultadoPaymentDispute::Aprovado) {
            $servico->status = StatusServico::Aprovado;
            $servico->save();

            return;
        }

        $servico->status = StatusServico::Cancelado;
        $servico->save();
        $this->releaseAuthorization($servico);
    }

    private function resolveCancelamentoExecucao(Servico $servico, ResultadoPaymentDispute $resultado): void
    {
        if ($resultado === ResultadoPaymentDispute::Aprovado) {
            $servico->status = StatusServico::EmAndamento;
            $servico->save();

            return;
        }

        $servico->status = StatusServico::Cancelado;
        $servico->save();
        $this->releaseAuthorization($servico);
    }

    private function releaseAuthorization(Servico $servico): void
    {
        $authorization = PaymentAuthorization::query()
            ->where('servico_id', $servico->id)
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->latest('criado_em')
            ->first();

        if ($authorization !== null) {
            ($this->cancelAuthorized)($authorization, ['motivo' => 'DISPUTA_RESOLVIDA']);
        }
    }
}
