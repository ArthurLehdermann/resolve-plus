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
        return DB::transaction(function () use ($servico, $usuario): Servico {
            $servico = $this->locked($servico);

            if (! $servico->isClienteDono($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o cliente do serviço pode aprovar a conclusão.',
                );
            }

            return $this->transition($servico, automatico: false);
        });
    }

    public function bySystem(Servico $servico): Servico
    {
        return DB::transaction(function () use ($servico): Servico {
            return $this->transition($this->locked($servico), automatico: true);
        });
    }

    private function locked(Servico $servico): Servico
    {
        return Servico::query()
            ->whereKey($servico->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function transition(Servico $servico, bool $automatico): Servico
    {
        if ($servico->status === StatusServico::Aprovado) {
            return $servico;
        }

        if ($servico->status !== StatusServico::AguardandoAprovacao) {
            throw ServiceException::conflict(
                'Somente serviços aguardando aprovação podem ser aprovados.',
            );
        }

        $servico->status = StatusServico::Aprovado;
        $servico->save();

        ServiceApproved::dispatch($servico, $automatico);

        return $servico->refresh();
    }
}
