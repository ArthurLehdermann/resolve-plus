<?php

namespace App\Services\Actions;

use App\Auth\Enums\StatusConta;
use App\Auth\Models\Usuario;
use App\Services\Exceptions\ServiceException;
use App\Services\Mensagem;
use App\Services\Servico;
use App\Trust\ContactLeakEnforcer;
use App\Trust\Enums\OrigemVazamentoContato;

class StoreMessage
{
    public function __construct(private readonly ContactLeakEnforcer $enforcer) {}

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

        if ($usuario->status !== StatusConta::Ativa) {
            throw ServiceException::forbidden(
                'Apenas contas ativas podem enviar mensagens.',
            );
        }

        $enforcement = $this->enforcer->apply(
            usuario: $usuario,
            origem: OrigemVazamentoContato::Mensagem,
            text: $payload['text'],
            servicoId: $servico->id,
        );

        $mensagem = Mensagem::query()->create([
            'servico_id' => $servico->id,
            'remetente_id' => $usuario->id,
            'texto' => $enforcement['sanitized'],
            'texto_original' => $enforcement['changed'] ? $payload['text'] : null,
            'anexo' => $payload['attachment'] ?? null,
        ]);

        $mensagem->contactLeakWarning = $enforcement['warning'];

        return $mensagem;
    }
}
