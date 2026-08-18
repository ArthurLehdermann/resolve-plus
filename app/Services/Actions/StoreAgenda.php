<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Agenda;
use App\Services\Exceptions\ServiceException;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class StoreAgenda
{
    /**
     * @param  array{service_id: string, date: string, time: string, notes?: string|null}  $payload
     */
    public function __invoke(Usuario $usuario, array $payload): Agenda
    {
        try {
            return DB::transaction(function () use ($usuario, $payload): Agenda {
                $servico = Servico::query()
                    ->whereKey($payload['service_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $servico->isParticipante($usuario)) {
                    throw ServiceException::forbidden(
                        'Apenas o cliente ou o profissional deste serviço podem agendar.',
                    );
                }

                if ($servico->status !== StatusServico::Agendado) {
                    throw ServiceException::conflict(
                        'Só é possível agendar um serviço em estado Agendado.',
                    );
                }

                return Agenda::query()->create([
                    'servico_id' => $servico->id,
                    'data' => $payload['date'],
                    'hora' => $this->normalizeTime($payload['time']),
                    'observacoes' => $payload['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ServiceException::conflict(
                'Este serviço já possui um agendamento. Use PUT /schedule/{id} para reagendar.',
            );
        }
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
