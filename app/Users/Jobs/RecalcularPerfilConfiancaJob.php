<?php

namespace App\Users\Jobs;

use App\Users\RecalcularPerfilConfianca;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalcularPerfilConfiancaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $profissionalId) {}

    public function handle(RecalcularPerfilConfianca $recalcular): void
    {
        $recalcular($this->profissionalId);
    }
}
