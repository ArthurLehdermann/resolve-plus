<?php

namespace App\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * STUB W10 — captura de pagamento (P4 / INV-041).
 *
 * O evento `ServiceApproved` dispara este job para não perder o gatilho.
 * O handler concreto (autorização vigente → CAPTURADO, split, gateway Asaas)
 * depende do bounded context Payment (W10) ainda inexistente.
 *
 * Contestação (`PaymentDispute` ABERTA, INV-045) bloqueia captura/repasse
 * porque o serviço sai de `AGUARDANDO_APROVACAO` antes deste job ser
 * disparado; quando W10 existir, o handler deve recusar captura se houver
 * disputa aberta.
 */
class CapturePaymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $servicoId) {}

    public function handle(): void
    {
        Log::info('P4 stub: captura de pagamento pendente de W10', [
            'servico_id' => $this->servicoId,
        ]);
    }
}
