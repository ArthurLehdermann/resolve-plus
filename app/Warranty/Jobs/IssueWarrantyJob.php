<?php

namespace App\Warranty\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * STUB W11 — emissão de garantia (P5 / INV-050).
 *
 * Toda aprovação dispara este job. A entidade `Garantia` (prazo herdado da
 * proposta, INV-051) será criada quando o módulo Warranty (W11) existir.
 */
class IssueWarrantyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $servicoId) {}

    public function handle(): void
    {
        Log::info('P5 stub: emissão de garantia pendente de W11', [
            'servico_id' => $this->servicoId,
        ]);
    }
}
