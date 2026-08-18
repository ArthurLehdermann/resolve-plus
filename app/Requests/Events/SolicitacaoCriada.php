<?php

namespace App\Requests\Events;

use App\Requests\Solicitacao;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitacaoCriada
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Solicitacao $solicitacao) {}
}
