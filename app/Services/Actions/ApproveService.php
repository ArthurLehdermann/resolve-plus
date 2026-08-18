<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Events\ServiceApproved;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class ApproveService
{
    public function byCliente(Servico $servico, Usuario $usuario): Servico
    {
        [$servico, $automatico, $disparar] = DB::transaction(function () use ($servico, $usuario): array {
            $servico = $this->locked($servico);

            if (! $servico->isClienteDono($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o cliente do serviço pode aprovar a conclusão.',
                );
            }

            return $this->transition($servico, automatico: false);
        });

        if ($disparar) {
            ServiceApproved::dispatch($servico, $automatico);
        }

        return $servico;
    }

    public function bySystem(Servico $servico): Servico
    {
        [$servico, $automatico, $disparar] = DB::transaction(function () use ($servico): array {
            return $this->transition($this->locked($servico), automatico: true);
        });

        if ($disparar) {
            ServiceApproved::dispatch($servico, $automatico);
        }

        return $servico;
    }

    private function locked(Servico $servico): Servico
    {
        return Servico::query()
            ->whereKey($servico->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{0: Servico, 1: bool, 2: bool}
     */
    private function transition(Servico $servico, bool $automatico): array
    {
        if ($servico->status === StatusServico::Aprovado) {
            return [$servico, $automatico, false];
        }

        if ($servico->status !== StatusServico::AguardandoAprovacao) {
            throw ServiceException::conflict(
                'Somente serviços aguardando aprovação podem ser aprovados.',
            );
        }

        $servico->status = StatusServico::Aprovado;
        $servico->save();

        return [$servico->refresh(), $automatico, true];
    }
}
