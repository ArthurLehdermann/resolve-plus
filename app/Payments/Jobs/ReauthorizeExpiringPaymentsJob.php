<?php

namespace App\Payments\Jobs;

use App\Payments\ReauthorizeExpiringPayments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReauthorizeExpiringPaymentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ReauthorizeExpiringPayments $reauthorize): void
    {
        $reauthorize();
    }
}
