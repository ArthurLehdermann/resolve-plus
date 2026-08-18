<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Events\ServiceStarted;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;

class StartService
{
    public function __invoke(Servico $servico, Usuario $usuario): Servico
    {
        return DB::transaction(function () use ($servico, $usuario): Servico {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $servico->isProfissionalResponsavel($usuario)) {
                throw ServiceException::forbidden(
                    'Apenas o profissional da proposta aceita pode iniciar este serviço.',
                );
            }

            if ($servico->status !== StatusServico::Agendado) {
                throw ServiceException::conflict(
                    'Somente serviços agendados podem ser iniciados.',
                );
            }

            $servico->status = StatusServico::EmAndamento;
            $servico->inicio = now();
            $servico->save();

            ServiceStarted::dispatch($servico);

            return $servico->refresh();
        });
    }
}
