<?php

namespace App\Payments\Jobs;

use App\Payments\ExpirePendingPixPayments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePendingPixPaymentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ExpirePendingPixPayments $expire): void
    {
        $expire();
    }
}
