<?php

namespace App\Payments;

use App\Payments\Gateway\PaymentGateway;
use Illuminate\Support\Facades\DB;

class ApplyCancellationPenalty
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
        private readonly CancelAuthorizedPayment $cancelAuthorized,
        private readonly CommissionRate $commissionRate,
        private readonly SplitCalculator $splitCalculator,
    ) {}

    /**
     * @return array{authorization: PaymentAuthorization, multa: array{percentual: int, valor_centavos: int}}
     */
    public function __invoke(PaymentAuthorization $authorization, int $valorMulta, int $valorProposta, int $percentual): array
    {
        // Pix ainda PENDENTE (webhook do Asaas não confirmou) não tem
        // dinheiro nenhum retido - não existe multa possível sobre um
        // pagamento que nunca aconteceu. Cancela a cobrança pendente no
        // gateway e encerra, independente do percentual calculado.
        if ($authorization->metodo === MetodoPagamento::Pix && $authorization->status === StatusPaymentAuthorization::Pendente) {
            $authorization = ($this->cancelAuthorized)($authorization, [
                'motivo' => 'CANCELAMENTO_PIX_NAO_CONFIRMADO',
            ]);

            return ['authorization' => $authorization, 'multa' => ['percentual' => 0, 'valor_centavos' => 0]];
        }

        $multa = [
            'percentual' => $percentual,
            'valor_centavos' => $valorMulta,
        ];

        if ($valorMulta === 0) {
            $authorization = ($this->cancelAuthorized)($authorization, [
                'motivo' => 'CANCELAMENTO_SEM_MULTA',
            ]);

            return ['authorization' => $authorization, 'multa' => $multa];
        }

        if ($authorization->metodo === MetodoPagamento::Pix) {
            return [
                'authorization' => $this->applyPixPartialRefund($authorization, $valorMulta, $valorProposta),
                'multa' => $multa,
            ];
        }

        return [
            'authorization' => $this->applyCardPartialCapture($authorization, $valorMulta),
            'multa' => $multa,
        ];
    }

    private function applyCardPartialCapture(PaymentAuthorization $authorization, int $valorMulta): PaymentAuthorization
    {
        return DB::transaction(function () use ($authorization, $valorMulta): PaymentAuthorization {
            $locked = PaymentAuthorization::query()
                ->lockForUpdate()
                ->findOrFail($authorization->id);

            if ($locked->status === StatusPaymentAuthorization::Capturado) {
                return $locked;
            }

            if ($locked->status !== StatusPaymentAuthorization::Autorizado) {
                throw new PaymentDomainException('Autorização não está AUTORIZADO para captura parcial.');
            }

            $aliquota = $this->commissionRate->current();
            $split = $this->splitCalculator->calculate($valorMulta, $aliquota);
            $gatewaySplits = [];

            if ($locked->wallet_id !== null) {
                $gatewaySplits[] = [
                    'walletId' => $locked->wallet_id,
                    'percentualValue' => 100 - $aliquota,
                ];
            }

            if ($locked->gateway_payment_id !== null) {
                $this->gateway->capture($locked->gateway_payment_id, $valorMulta, $gatewaySplits);
                $this->gateway->cancel($locked->gateway_payment_id);
            }

            $event = ($this->recordEvent)($locked, TipoPaymentEvent::Capturado, [
                'motivo' => 'CANCELAMENTO_MULTA',
                'valor' => $valorMulta,
            ]);

            PaymentSplit::query()->create([
                'payment_event_id' => $event->id,
                ...$split,
            ]);

            return $locked->refresh();
        });
    }

    private function applyPixPartialRefund(
        PaymentAuthorization $authorization,
        int $valorMulta,
        int $valorProposta,
    ): PaymentAuthorization {
        return DB::transaction(function () use ($authorization, $valorMulta, $valorProposta): PaymentAuthorization {
            $locked = PaymentAuthorization::query()
                ->lockForUpdate()
                ->findOrFail($authorization->id);

            if ($locked->status !== StatusPaymentAuthorization::Capturado) {
                throw new PaymentDomainException('Pix deve estar CAPTURADO para reembolso parcial.');
            }

            $captureEvent = $locked->captureEvent();

            if ($captureEvent === null) {
                throw new PaymentDomainException('Evento CAPTURADO ausente para Pix.');
            }

            $valorReembolso = $valorProposta - $valorMulta;

            if ($valorReembolso > 0) {
                PaymentRefund::query()->create([
                    'payment_event_id' => $captureEvent->id,
                    'valor' => $valorReembolso,
                    'motivo' => 'CANCELAMENTO_MULTA_PIX',
                ]);

                ($this->recordEvent)($locked, TipoPaymentEvent::Reembolsado, [
                    'motivo' => 'CANCELAMENTO_MULTA_PIX',
                    'valor' => $valorReembolso,
                ]);
            }

            return $locked->refresh();
        });
    }
}
