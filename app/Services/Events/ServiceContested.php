<?php

namespace App\Services\Events;

use App\Payments\PaymentDispute;
use App\Services\Servico;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceContested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Servico $servico,
        public PaymentDispute $dispute,
    ) {}
}
