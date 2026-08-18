<?php

namespace App\Ratings\Events;

use App\Ratings\Avaliacao;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvaliacaoRegistrada
{
    use Dispatchable, SerializesModels;

    public function __construct(public Avaliacao $avaliacao) {}
}
