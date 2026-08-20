<?php

namespace App\Payments;

use App\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\DB;

class CancelAuthorizedPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(PaymentAuthorization $authorization, array $payload = []): PaymentAuthorization
    {
        if ($authorization->status->isTerminal()) {
            return $authorization;
        }

        if (! in_array($authorization->status, [StatusPaymentAuthorization::Autorizado, StatusPaymentAuthorization::Pendente], true)) {
            throw new PaymentDomainException('Somente autorização AUTORIZADO ou PENDENTE pode ser cancelada.');
        }

        return DB::transaction(function () use ($authorization, $payload): PaymentAuthorization {
            $locked = PaymentAuthorization::query()
                ->lockForUpdate()
                ->findOrFail($authorization->id);

            if ($locked->status->isTerminal()) {
                return $locked;
            }

            if ($locked->gateway_payment_id !== null) {
                $this->gateway->cancel($locked->gateway_payment_id);
            }

            ($this->recordEvent)($locked, TipoPaymentEvent::Cancelado, $payload);

            return $locked->refresh();
        });
    }
}
