<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Services\Events\ServiceContested;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class OpenDispute
{
    public function __invoke(Servico $servico, Usuario $usuario, TipoPaymentDispute $tipo, ?string $motivo = null): PaymentDispute
    {
        [$servico, $dispute] = DB::transaction(function () use ($servico, $usuario, $tipo, $motivo): array {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $servico->isParticipante($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas cliente ou profissional do serviço podem abrir disputa.',
                );
            }

            $aberta = PaymentDispute::query()
                ->where('servico_id', $servico->id)
                ->where('tipo', $tipo)
                ->where('status', StatusPaymentDispute::Aberta)
                ->lockForUpdate()
                ->first();

            if ($aberta !== null) {
                return [$servico, $aberta];
            }

            if ($servico->status !== StatusServico::EmContestacao) {
                throw ServiceException::conflict('Serviço deve estar em contestação para abrir disputa.');
            }

            $dispute = PaymentDispute::query()->create([
                'servico_id' => $servico->id,
                'tipo' => $tipo,
                'status' => StatusPaymentDispute::Aberta,
                'motivo' => $motivo,
                'aberta_em' => now(),
            ]);

            return [$servico->refresh(), $dispute];
        });

        ServiceContested::dispatch($servico, $dispute);

        return $dispute;
    }
}
