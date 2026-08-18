<?php

namespace App\Proposals\Events;

use App\Proposals\Proposta;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Proposta $proposta) {}
}
