<?php

namespace App\Payments\Jobs;

use App\Payments\Actions\ResolveExpiredDisputes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResolveExpiredDisputesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function handle(ResolveExpiredDisputes $resolve): void
    {
        $resolve();
    }
}
