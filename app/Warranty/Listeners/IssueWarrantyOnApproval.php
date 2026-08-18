<?php

namespace App\Warranty\Listeners;

use App\Services\Events\ServiceApproved;
use App\Warranty\Jobs\IssueWarrantyJob;

class IssueWarrantyOnApproval
{
    public function handle(ServiceApproved $event): void
    {
        IssueWarrantyJob::dispatch($event->servico->id);
    }
}
