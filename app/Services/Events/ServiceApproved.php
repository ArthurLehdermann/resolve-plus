<?php

namespace App\Services\Events;

use App\Services\Servico;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Servico $servico,
        public bool $automatico = false,
    ) {}
}
