<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Agenda;
use App\Services\Exceptions\ServiceException;
use App\Services\StatusServico;

class RescheduleAgenda
{
    /**
     * @param  array{date: string, time: string, notes?: string|null}  $payload
     */
    public function __invoke(Agenda $agenda, Usuario $usuario, array $payload): Agenda
    {
        $agenda->loadMissing('servico.proposta.solicitacao');
        $servico = $agenda->servico;

        if (! $servico->isParticipante($usuario)) {
            throw ServiceException::forbidden(
                'Apenas o cliente ou o profissional deste serviço podem reagendar.',
            );
        }

        if ($servico->status !== StatusServico::Agendado) {
            throw ServiceException::conflict(
                'Só é possível reagendar um serviço em estado Agendado.',
            );
        }

        $agenda->data = $payload['date'];
        $agenda->hora = strlen($payload['time']) === 5 ? $payload['time'].':00' : $payload['time'];

        if (array_key_exists('notes', $payload)) {
            $agenda->observacoes = $payload['notes'];
        }

        $agenda->save();

        return $agenda->refresh();
    }
}
