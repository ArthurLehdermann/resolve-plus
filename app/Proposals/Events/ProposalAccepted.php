<?php

namespace App\Proposals\Events;

use App\Proposals\Proposta;
use App\Services\Servico;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalAccepted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Proposta $proposta,
        public Servico $servico,
    ) {}
}
