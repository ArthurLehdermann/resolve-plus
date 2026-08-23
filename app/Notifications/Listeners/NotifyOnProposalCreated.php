<?php

namespace App\Notifications\Listeners;

use App\Notifications\NotifyUser;
use App\Notifications\TipoNotificacao;
use App\Proposals\Events\ProposalCreated;

class NotifyOnProposalCreated
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(ProposalCreated $event): void
    {
        $proposta = $event->proposta;
        $proposta->loadMissing('solicitacao');

        $solicitacao = $proposta->solicitacao;

        if ($solicitacao === null) {
            return;
        }

        ($this->notify)(
            (string) $solicitacao->cliente_id,
            TipoNotificacao::PropostaRecebida,
            'Você recebeu uma proposta',
            'Um profissional enviou proposta para a sua solicitação. Compare e escolha quando quiser.',
            ['solicitacao_id' => (string) $solicitacao->id, 'proposta_id' => (string) $proposta->id],
        );
    }
}
