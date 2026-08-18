<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Payments\ApplyCancellationPenalty;
use App\Payments\CancellationPenalty;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Services\Events\ServiceContested;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CancelService
{
    public function __construct(
        private readonly CancellationPenalty $penalty,
        private readonly ApplyCancellationPenalty $applyPenalty,
    ) {}

    /**
     * @return array{servico: Servico, multa: array{percentual: int, valor_centavos: int}|null, dispute: PaymentDispute|null}
     */
    public function __invoke(Servico $servico, Usuario $usuario, ?string $motivo = null): array
    {
        $servico->loadMissing('proposta.solicitacao');

        if ($servico->status === StatusServico::EmContestacao) {
            return $this->disputaExecucaoExistente($servico, $usuario);
        }

        return match ($servico->status) {
            StatusServico::Agendado => $this->cancelAgendado($servico, $usuario),
            StatusServico::EmAndamento => $this->openDisputaExecucao($servico, $usuario, $motivo),
            default => throw ServiceException::conflict(
                'Cancelamento não permitido no estado atual do serviço.',
            ),
        };
    }

    /**
     * @return array{servico: Servico, multa: array{percentual: int, valor_centavos: int}|null, dispute: PaymentDispute|null}
     */
    private function cancelAgendado(Servico $servico, Usuario $usuario): array
    {
        if (! $servico->isClienteDono($usuario)) {
            throw ServiceException::forbidden('Apenas o cliente pode cancelar o serviço agendado.');
        }

        [$servico, $multa] = DB::transaction(function () use ($servico): array {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($servico->status === StatusServico::Cancelado) {
                return [$servico, null];
            }

            if ($servico->status !== StatusServico::Agendado) {
                throw ServiceException::conflict('Serviço não está agendado.');
            }

            $servico->loadMissing('proposta');
            $referencia = $this->referenciaAgendamento($servico);
            $multaCalc = $this->penalty->calculate(
                (int) $servico->proposta->valor,
                $referencia,
            );

            $authorization = PaymentAuthorization::query()
                ->where('servico_id', $servico->id)
                ->whereIn('status', [
                    StatusPaymentAuthorization::Autorizado,
                    StatusPaymentAuthorization::Capturado,
                ])
                ->lockForUpdate()
                ->latest('criado_em')
                ->first();

            if ($authorization !== null) {
                ($this->applyPenalty)(
                    $authorization,
                    $multaCalc['valor_centavos'],
                    (int) $servico->proposta->valor,
                    $multaCalc['percentual'],
                );
            }

            $servico->status = StatusServico::Cancelado;
            $servico->save();

            return [$servico->refresh(), $multaCalc];
        });

        return [
            'servico' => $servico,
            'multa' => $multa,
            'dispute' => null,
        ];
    }

    /**
     * @return array{servico: Servico, multa: null, dispute: PaymentDispute|null}
     */
    private function disputaExecucaoExistente(Servico $servico, Usuario $usuario): array
    {
        $isParticipante = $servico->isClienteDono($usuario) || $servico->isProfissionalResponsavel($usuario);

        if (! $isParticipante) {
            throw ServiceException::forbidden('Apenas cliente ou profissional do serviço podem solicitar cancelamento.');
        }

        $dispute = PaymentDispute::query()
            ->where('servico_id', $servico->id)
            ->where('tipo', TipoPaymentDispute::CancelamentoExecucao)
            ->where('status', StatusPaymentDispute::Aberta)
            ->first();

        if ($dispute === null) {
            throw ServiceException::conflict('Serviço em contestação sem disputa de cancelamento aberta.');
        }

        return [
            'servico' => $servico,
            'multa' => null,
            'dispute' => $dispute,
        ];
    }

    /**
     * @return array{servico: Servico, multa: null, dispute: PaymentDispute|null}
     */
    private function openDisputaExecucao(Servico $servico, Usuario $usuario, ?string $motivo): array
    {
        $isParticipante = $servico->isClienteDono($usuario) || $servico->isProfissionalResponsavel($usuario);

        if (! $isParticipante) {
            throw ServiceException::forbidden('Apenas cliente ou profissional do serviço podem solicitar cancelamento.');
        }

        [$servico, $dispute] = DB::transaction(function () use ($servico, $motivo): array {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            $aberta = PaymentDispute::query()
                ->where('servico_id', $servico->id)
                ->where('tipo', TipoPaymentDispute::CancelamentoExecucao)
                ->where('status', StatusPaymentDispute::Aberta)
                ->lockForUpdate()
                ->first();

            if ($servico->status === StatusServico::EmContestacao && $aberta !== null) {
                return [$servico, null];
            }

            if ($servico->status !== StatusServico::EmAndamento) {
                throw ServiceException::conflict('Somente serviços em andamento abrem disputa de cancelamento.');
            }

            $servico->status = StatusServico::EmContestacao;
            $servico->save();

            $dispute = $aberta ?? PaymentDispute::query()->create([
                'servico_id' => $servico->id,
                'tipo' => TipoPaymentDispute::CancelamentoExecucao,
                'status' => StatusPaymentDispute::Aberta,
                'motivo' => $motivo,
                'aberta_em' => now(),
            ]);

            return [$servico->refresh(), $dispute];
        });

        if ($dispute !== null) {
            ServiceContested::dispatch($servico, $dispute);
        }

        return [
            'servico' => $servico,
            'multa' => null,
            'dispute' => $dispute,
        ];
    }

    private function referenciaAgendamento(Servico $servico): CarbonImmutable
    {
        if ($servico->inicio !== null) {
            return CarbonImmutable::instance($servico->inicio);
        }

        $servico->loadMissing('proposta');

        return CarbonImmutable::instance($servico->created_at)
            ->addDays(max(1, (int) $servico->proposta->prazo_dias));
    }
}
