<?php

namespace App\Payments\Actions;

use App\Auth\Models\Usuario;
use App\Payments\Auditoria;
use App\Payments\CancelAuthorizedPayment;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\PaymentDomainException;
use App\Payments\ResultadoPaymentDispute;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Services\Events\ServiceApproved;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class ResolveDispute
{
    public function __construct(
        private readonly CancelAuthorizedPayment $cancelAuthorized,
    ) {}

    public function __invoke(
        PaymentDispute $dispute,
        Usuario $admin,
        ResultadoPaymentDispute $resultado,
        string $justificativa,
        ?string $ip = null,
    ): PaymentDispute {
        $justificativa = trim($justificativa);

        if ($justificativa === '') {
            throw new PaymentDomainException('Justificativa obrigatória (INV-070).', 422);
        }

        if ($admin->tipo->value !== 'ADMIN') {
            throw ServiceException::forbidden('Apenas administradores podem resolver disputas.');
        }

        return DB::transaction(function () use ($dispute, $admin, $resultado, $justificativa, $ip): PaymentDispute {
            $dispute = PaymentDispute::query()
                ->whereKey($dispute->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispute->status === StatusPaymentDispute::Resolvida) {
                throw ServiceException::conflict('Disputa já resolvida.');
            }

            $servico = Servico::query()
                ->whereKey($dispute->servico_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($servico->status !== StatusServico::EmContestacao) {
                throw ServiceException::conflict('Serviço deve estar em contestação.');
            }

            $this->applyResolution($servico, $dispute, $resultado);

            $dispute->status = StatusPaymentDispute::Resolvida;
            $dispute->resultado = $resultado;
            $dispute->justificativa = $justificativa;
            $dispute->resolvida_em = now();
            $dispute->resolvida_por_id = $admin->id;
            $dispute->save();

            Auditoria::query()->create([
                'usuario_id' => $admin->id,
                'acao' => 'disputes.resolve',
                'entidade' => 'PaymentDispute',
                'id_entidade' => $dispute->id,
                'ip' => $ip,
                'justificativa' => $justificativa,
            ]);

            if ($servico->fresh()->status === StatusServico::Aprovado) {
                ServiceApproved::dispatch($servico->fresh(), automatico: false);
            }

            return $dispute->refresh();
        });
    }

    private function applyResolution(
        Servico $servico,
        PaymentDispute $dispute,
        ResultadoPaymentDispute $resultado,
    ): void {
        match ($dispute->tipo) {
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
