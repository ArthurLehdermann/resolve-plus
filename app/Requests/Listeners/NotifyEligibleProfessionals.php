<?php

namespace App\Requests\Listeners;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Requests\Events\SolicitacaoCriada;
use Illuminate\Support\Facades\Log;

/**
 * Stub síncrono do evento P (Notificar profissionais elegíveis).
 *
 * A busca geográfica por bounding box (`04-modelo-dados.md` §Busca geográfica)
 * ainda não está pronta (não há Endereco de atuação do profissional persistido).
 * No MVP este listener só registra a intenção e a contagem de profissionais
 * ATIVA (INV-002), sem filtrar por categoria/raio nem enviar push.
 */
class NotifyEligibleProfessionals
{
    public function handle(SolicitacaoCriada $event): void
    {
        $elegiveis = Usuario::query()
            ->where('tipo', TipoUsuario::Profissional)
            ->where('status', StatusConta::Ativa)
            ->count();

        Log::info('solicitacao.created.notify_professionals.stub', [
            'solicitacao_id' => $event->solicitacao->id,
            'categoria_id' => $event->solicitacao->categoria_id,
            'property_id' => $event->solicitacao->property_id,
            'professionals_ativa' => $elegiveis,
            'limitation' => 'Busca geográfica com bounding box ainda não está pronta; notificação de profissionais na categoria/raio é stub síncrono.',
        ]);
    }
}
