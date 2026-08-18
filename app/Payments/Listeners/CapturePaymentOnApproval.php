<?php

namespace App\Payments\Listeners;

use App\Payments\Jobs\CapturePaymentJob;
use App\Services\Events\ServiceApproved;

class CapturePaymentOnApproval
{
    public function handle(ServiceApproved $event): void
    {
        CapturePaymentJob::dispatch($event->servico->id);
    }
}
