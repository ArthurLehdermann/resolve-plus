<?php

namespace App\Services\Actions;

use App\Auth\Models\Usuario;
use App\Services\Exceptions\ServiceException;
use App\Services\Mensagem;
use App\Services\Servico;

class StoreMessage
{
    /**
     * @param  array{text: string, attachment?: string|null}  $payload
     */
    public function __invoke(Servico $servico, Usuario $usuario, array $payload): Mensagem
    {
        if (! $servico->isParticipante($usuario)) {
            throw ServiceException::forbidden(
                'Apenas o cliente ou o profissional deste serviço podem enviar mensagens.',
            );
        }

        return Mensagem::query()->create([
            'servico_id' => $servico->id,
            'remetente_id' => $usuario->id,
            'texto' => $payload['text'],
            'anexo' => $payload['attachment'] ?? null,
        ]);
    }
}
