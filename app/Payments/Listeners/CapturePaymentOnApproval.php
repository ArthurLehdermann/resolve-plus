<?php

namespace App\Payments\Listeners;

use App\Payments\Jobs\CapturePaymentJob;
use App\Services\Events\ServiceApproved;

class CapturePaymentOnApproval
{
    public function handle(ServiceApproved $event): void
    {
        if ($event->servico->isRevisitaGarantia()) {
            return;
        }

        CapturePaymentJob::dispatch($event->servico->id);
    }
}
