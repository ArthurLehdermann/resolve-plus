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

class ContestService
{
    public function __invoke(Servico $servico, Usuario $usuario, string $motivo): Servico
    {
        return DB::transaction(function () use ($servico, $usuario, $motivo): Servico {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $servico->isClienteDono($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o cliente do serviço pode contestar a conclusão.',
                );
            }

            $aberta = PaymentDispute::query()
                ->where('servico_id', $servico->id)
                ->where('tipo', TipoPaymentDispute::ContestacaoConclusao)
                ->where('status', StatusPaymentDispute::Aberta)
                ->lockForUpdate()
                ->first();

            if ($servico->status === StatusServico::EmContestacao && $aberta !== null) {
                return $servico;
            }

            if ($servico->status !== StatusServico::AguardandoAprovacao) {
                throw ServiceException::conflict(
                    'Somente serviços aguardando aprovação podem ser contestados.',
                );
            }

            $servico->status = StatusServico::EmContestacao;
            $servico->save();

            $dispute = $aberta ?? PaymentDispute::query()->create([
                'servico_id' => $servico->id,
                'tipo' => TipoPaymentDispute::ContestacaoConclusao,
                'status' => StatusPaymentDispute::Aberta,
                'motivo' => $motivo,
                'aberta_em' => now(),
            ]);

            ServiceContested::dispatch($servico, $dispute);

            return $servico->refresh();
        });
    }
}
