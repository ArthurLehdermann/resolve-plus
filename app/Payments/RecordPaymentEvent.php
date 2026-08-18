<?php

namespace App\Payments;

use Illuminate\Support\Facades\DB;

class RecordPaymentEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(PaymentAuthorization $authorization, TipoPaymentEvent $tipo, array $payload = []): PaymentEvent
    {
        return DB::transaction(function () use ($authorization, $tipo, $payload): PaymentEvent {
            $event = PaymentEvent::query()->create([
                'payment_authorization_id' => $authorization->id,
                'tipo' => $tipo,
                'payload' => $payload,
            ]);

            $projected = $tipo->projectedStatus();

            if ($projected !== null && $authorization->status !== $projected) {
                $authorization->forceFill(['status' => $projected])->save();
            }

            return $event;
        });
    }
}
