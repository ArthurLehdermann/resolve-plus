<?php

namespace App\Ratings\Listeners;

use App\Ratings\DirecaoAvaliacao;
use App\Ratings\Events\AvaliacaoRegistrada;
use App\Users\Jobs\RecalcularPerfilConfiancaJob;

class RecalcularPerfilOnAvaliacao
{
    public function handle(AvaliacaoRegistrada $event): void
    {
        if ($event->avaliacao->direcao !== DirecaoAvaliacao::ClienteAvaliaProfissional) {
            return;
        }

        RecalcularPerfilConfiancaJob::dispatch($event->avaliacao->alvo_id);
    }
}
