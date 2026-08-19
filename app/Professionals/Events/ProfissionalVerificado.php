<?php

namespace App\Professionals\Events;

use App\Auth\Models\Usuario;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfissionalVerificado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Usuario $profissional,
    ) {}
}
