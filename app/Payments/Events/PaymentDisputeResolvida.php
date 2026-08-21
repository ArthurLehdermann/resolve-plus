<?php

namespace App\Payments\Events;

use App\Payments\PaymentDispute;
use App\Services\Servico;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentDisputeResolvida
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PaymentDispute $dispute,
        public Servico $servico,
    ) {}
}
