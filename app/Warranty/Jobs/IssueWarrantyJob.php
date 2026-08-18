<?php

namespace App\Warranty\Jobs;

use App\Services\Servico;
use App\Warranty\Actions\IssueWarranty;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IssueWarrantyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $servicoId) {}

    public function handle(IssueWarranty $issueWarranty): void
    {
        $servico = Servico::query()->findOrFail($this->servicoId);
        $issueWarranty($servico);
    }
}
