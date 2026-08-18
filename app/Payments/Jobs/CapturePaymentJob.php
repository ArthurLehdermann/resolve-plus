<?php

namespace App\Payments\Jobs;

use App\Payments\CapturePayment;
use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\PaymentDomainException;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CapturePaymentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $servicoId) {}

    public function handle(CapturePayment $capture): void
    {
        $servico = Servico::query()->find($this->servicoId);

        if ($servico === null || $servico->status !== StatusServico::Aprovado) {
            return;
        }

        $openDispute = PaymentDispute::query()
            ->where('servico_id', $servico->id)
            ->where('status', StatusPaymentDispute::Aberta)
            ->exists();

        if ($openDispute) {
            Log::info('Captura bloqueada: disputa aberta (INV-045).', ['servico_id' => $servico->id]);

            return;
        }

        $authorization = PaymentAuthorization::query()
            ->where('servico_id', $servico->id)
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->where('metodo', MetodoPagamento::Cartao)
            ->latest('criado_em')
            ->first();

        if ($authorization === null) {
            return;
        }

        try {
            $capture($authorization, ['motivo' => 'SERVICO_APROVADO']);
        } catch (PaymentDomainException $exception) {
            Log::warning('Captura recusada após aprovação do serviço.', [
                'servico_id' => $this->servicoId,
                'authorization_id' => $authorization->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
