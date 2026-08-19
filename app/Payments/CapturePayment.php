<?php

namespace App\Payments;

use App\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\DB;

class CapturePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
        private readonly CommissionRate $commissionRate,
        private readonly SplitCalculator $splitCalculator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(PaymentAuthorization $authorization, array $payload = []): PaymentAuthorization
    {
        if ($authorization->status === StatusPaymentAuthorization::Capturado) {
            return $authorization;
        }

        if ($authorization->status !== StatusPaymentAuthorization::Autorizado) {
            throw new PaymentDomainException('Autorização não está AUTORIZADO; captura recusada.');
        }

        return DB::transaction(function () use ($authorization, $payload): PaymentAuthorization {
            $locked = PaymentAuthorization::query()
                ->with('servico')
                ->lockForUpdate()
                ->findOrFail($authorization->id);

            if ($locked->status === StatusPaymentAuthorization::Capturado) {
                return $locked;
            }

            if ($locked->status !== StatusPaymentAuthorization::Autorizado) {
                throw new PaymentDomainException('Autorização não está AUTORIZADO; captura recusada.');
            }

            $aliquota = $this->commissionRate->current();
            $split = $this->splitCalculator->calculate($locked->valor, $aliquota);
            $gatewaySplits = [];
            $walletId = $locked->wallet_id;

            if ($walletId) {
                $gatewaySplits[] = [
                    'walletId' => $walletId,
                    'percentualValue' => 100 - $aliquota,
                ];
            }

            if ($locked->gateway_payment_id !== null) {
                $this->gateway->capture($locked->gateway_payment_id, $locked->valor, $gatewaySplits);
            }

            $event = ($this->recordEvent)($locked, TipoPaymentEvent::Capturado, [
                'gateway_payment_id' => $locked->gateway_payment_id,
                ...$payload,
            ]);

            PaymentSplit::query()->create([
                'payment_event_id' => $event->id,
                ...$split,
            ]);

            return $locked->refresh();
        });
    }
}
