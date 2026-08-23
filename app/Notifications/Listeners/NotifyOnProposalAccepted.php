<?php

namespace App\Notifications\Listeners;

use App\Notifications\NotifyUser;
use App\Notifications\TipoNotificacao;
use App\Proposals\Events\ProposalAccepted;

class NotifyOnProposalAccepted
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function handle(ProposalAccepted $event): void
    {
        ($this->notify)(
            (string) $event->proposta->profissional_id,
            TipoNotificacao::PropostaAceita,
            'Sua proposta foi aceita',
            'O cliente aceitou a sua proposta. O serviço já está na sua lista.',
            ['servico_id' => (string) $event->servico->id, 'proposta_id' => (string) $event->proposta->id],
        );
    }
}
