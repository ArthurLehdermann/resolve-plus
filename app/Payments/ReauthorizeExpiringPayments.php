<?php

namespace App\Payments;

use App\Payments\Gateway\GatewayException;
use App\Payments\Gateway\PaymentGateway;
use App\Services\StatusServico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReauthorizeExpiringPayments
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentEvent $recordEvent,
        private readonly CapturePayment $capturePayment,
    ) {}

    public function __invoke(): int
    {
        $cutoff = now()->addHours((int) config('payments.reauthorize_before_hours'));

        $ids = PaymentAuthorization::query()
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->where('metodo', MetodoPagamento::Cartao)
            ->whereNotNull('expira_em')
            ->where('expira_em', '<=', $cutoff)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            $this->process((string) $id);
            $processed++;
        }

        return $processed;
    }

    private function process(string $authorizationId): void
    {
        $authorization = PaymentAuthorization::query()->with('servico')->find($authorizationId);

        if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Autorizado) {
            return;
        }

        $servico = $authorization->servico;

        if ($servico === null) {
            return;
        }

        if ($servico->status === StatusServico::Aprovado) {
            ($this->capturePayment)($authorization, ['motivo' => 'REAUTH_JOB_SERVICO_APROVADO']);

            return;
        }

        if ($servico->status === StatusServico::Cancelado) {
            $this->voidAuthorization($authorization);

            return;
        }

        $this->rotate($authorization);
    }

    private function voidAuthorization(PaymentAuthorization $authorization): void
    {
        if ($authorization->gateway_payment_id !== null) {
            try {
                $this->gateway->cancel($authorization->gateway_payment_id);
            } catch (GatewayException $exception) {
                Log::warning('Falha ao cancelar autorização expirada no gateway.', [
                    'authorization_id' => $authorization->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        ($this->recordEvent)($authorization, TipoPaymentEvent::Cancelado, [
            'motivo' => 'SERVICO_CANCELADO',
        ]);
    }

    private function rotate(PaymentAuthorization $old): void
    {
        if ($old->credit_card_token === null || $old->gateway_customer_id === null) {
            $this->expireWithoutReplacement($old, 'sem token ou customer para reautorizar');

            return;
        }

        $gatewayNew = $this->gateway->authorizeCard(
            $old->gateway_customer_id,
            $old->valor,
            $old->credit_card_token,
        );

        try {
            DB::transaction(function () use ($old, $gatewayNew): void {
                $locked = PaymentAuthorization::query()->lockForUpdate()->findOrFail($old->id);

                if ($locked->status !== StatusPaymentAuthorization::Autorizado) {
                    $this->gateway->cancel($gatewayNew->id);

                    return;
                }

                ($this->recordEvent)($locked, TipoPaymentEvent::Expirado, [
                    'motivo' => 'REAUTORIZACAO',
                    'proxima_autorizacao_gateway_id' => $gatewayNew->id,
                ]);

                $new = PaymentAuthorization::query()->create([
                    'servico_id' => $locked->servico_id,
                    'valor' => $locked->valor,
                    'metodo' => MetodoPagamento::Cartao,
                    'status' => StatusPaymentAuthorization::Autorizado,
                    'gateway_payment_id' => $gatewayNew->id,
                    'credit_card_token' => $locked->credit_card_token,
                    'gateway_customer_id' => $locked->gateway_customer_id,
                    'expira_em' => $gatewayNew->expiresAt ?? now()->addDays((int) config('payments.authorization_days')),
                ]);

                ($this->recordEvent)($new, TipoPaymentEvent::Autorizado, [
                    'gateway_payment_id' => $gatewayNew->id,
                ]);

                ($this->recordEvent)($new, TipoPaymentEvent::Reautorizado, [
                    'autorizacao_anterior_id' => $locked->id,
                ]);
            });
        } catch (\Throwable $exception) {
            try {
                $this->gateway->cancel($gatewayNew->id);
            } catch (GatewayException) {
                //
            }

            throw $exception;
        }

        if ($old->gateway_payment_id !== null) {
            try {
                $this->gateway->cancel($old->gateway_payment_id);
            } catch (GatewayException $exception) {
                Log::warning('Falha ao cancelar autorização antiga após reautorização.', [
                    'authorization_id' => $old->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function expireWithoutReplacement(PaymentAuthorization $authorization, string $motivo): void
    {
        if ($authorization->gateway_payment_id !== null) {
            try {
                $this->gateway->cancel($authorization->gateway_payment_id);
            } catch (GatewayException) {
                //
            }
        }

        ($this->recordEvent)($authorization, TipoPaymentEvent::Expirado, [
            'motivo' => $motivo,
        ]);
    }
}
