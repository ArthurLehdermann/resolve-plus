<?php

namespace App\Payments\Jobs;

use App\Payments\ReleaseApprovedPayments;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReleaseApprovedPaymentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ReleaseApprovedPayments $release): void
    {
        $release();
    }
}
