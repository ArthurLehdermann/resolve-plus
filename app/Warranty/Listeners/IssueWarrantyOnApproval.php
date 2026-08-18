<?php

namespace App\Warranty\Listeners;

use App\Services\Events\ServiceApproved;
use App\Warranty\Jobs\IssueWarrantyJob;

class IssueWarrantyOnApproval
{
    public function handle(ServiceApproved $event): void
    {
        if ($event->servico->isRevisitaGarantia()) {
            return;
        }

        IssueWarrantyJob::dispatch($event->servico->id);
    }
}
