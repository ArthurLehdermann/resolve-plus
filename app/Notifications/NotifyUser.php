<?php

namespace App\Notifications;

/**
 * Único ponto de criação de notificação. Os listeners falam com esta action, e
 * não com o model, para que push (Pós-MVP, `08-planejamento.md`) entre aqui um
 * dia sem tocar em cada listener.
 */
class NotifyUser
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function __invoke(
        string $usuarioId,
        TipoNotificacao $tipo,
        string $titulo,
        string $corpo,
        array $dados = [],
    ): Notificacao {
        return Notificacao::query()->create([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'corpo' => $corpo,
            'dados' => $dados === [] ? null : $dados,
        ]);
    }
}
