<?php

namespace App\Requests\Listeners;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Notifications\NotifyUser;
use App\Notifications\TipoNotificacao;
use App\Requests\Events\SolicitacaoCriada;
use App\Users\PerfilProfissional;
use Illuminate\Support\Facades\Log;

/**
 * Evento P do fluxo (RN010/RF011): notificar profissionais elegíveis.
 *
 * Elegível aqui é o profissional ATIVA (INV-002) que atende a categoria da
 * solicitação — a mesma regra do feed de oportunidades
 * (`RequestController::available`), para que a notificação não prometa nada que
 * o feed não vá mostrar.
 *
 * Fora do escopo: raio geográfico (RF010). Não existe endereço de atuação
 * persistido, então o alcance é por categoria e o log registra isso.
 */
class NotifyEligibleProfessionals
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(SolicitacaoCriada $event): void
    {
        $solicitacao = $event->solicitacao;
        $solicitacao->loadMissing('categoria');

        $codigo = $solicitacao->categoria?->codigo;

        if ($codigo === null) {
            return;
        }

        $elegiveis = Usuario::query()
            ->where('tipo', TipoUsuario::Profissional)
            ->where('status', StatusConta::Ativa)
            ->whereIn(
                'id',
                PerfilProfissional::query()
                    ->whereJsonContains('categorias_atendidas', $codigo)
                    ->select('usuario_id'),
            )
            ->pluck('id');

        $titulo = 'Nova solicitação em '.($solicitacao->categoria?->nome ?? 'uma categoria sua');

        foreach ($elegiveis as $profissionalId) {
            ($this->notify)(
                (string) $profissionalId,
                TipoNotificacao::SolicitacaoNova,
                $titulo,
                'Um cliente abriu uma solicitação que combina com o que você atende. Envie sua proposta.',
                ['solicitacao_id' => (string) $solicitacao->id],
            );
        }

        Log::info('solicitacao.created.notify_professionals', [
            'solicitacao_id' => $solicitacao->id,
            'categoria_codigo' => $codigo,
            'notificados' => $elegiveis->count(),
            'limitation' => 'Alcance por categoria; raio geográfico (RF010) depende de endereço de atuação, que não existe.',
        ]);
    }
}
