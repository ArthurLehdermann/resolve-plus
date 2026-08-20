<?php

namespace App\Payments\Webhooks;

use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\RecordPaymentEvent;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Processa o webhook de pagamentos do Asaas. É o único jeito legítimo de
 * uma autorização Pix sair de PENDENTE - CreatePaymentAuthorization nunca
 * grava CAPTURADO direto (ver docblock lá).
 *
 * Idempotência: cada evento é gravado em payment_webhook_events antes de
 * qualquer efeito colateral, com gateway_event_id UNIQUE. Se o Asaas
 * reentregar o mesmo evento (comportamento normal de webhook - reenvia até
 * receber 2xx), a segunda tentativa esbarra na constraint e não reprocessa.
 * Tudo dentro de uma única transação: ou o evento fica registrado E a
 * autorização foi atualizada, ou nenhum dos dois.
 */
class HandleAsaasWebhook
{
    private const STATUS_CONFIRMADO = ['CONFIRMED', 'RECEIVED'];

    private const STATUS_NAO_VAI_ACONTECER = ['OVERDUE', 'REFUNDED', 'CANCELLED'];

    private const EVENTOS_NAO_VAI_ACONTECER = ['PAYMENT_DELETED', 'PAYMENT_REFUNDED'];

    public function __construct(
        private readonly RecordPaymentEvent $recordEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(array $payload): void
    {
        $eventId = $this->eventId($payload);
        $eventType = (string) ($payload['event'] ?? '');
        $paymentId = (string) ($payload['payment']['id'] ?? '');
        $paymentStatus = (string) ($payload['payment']['status'] ?? '');

        try {
            DB::transaction(function () use ($eventId, $eventType, $paymentId, $paymentStatus, $payload): void {
                PaymentWebhookEvent::query()->create([
                    'provider' => 'asaas',
                    'gateway_event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload' => $payload,
                ]);

                if ($paymentId !== '') {
                    $this->applyToAuthorization($paymentId, $paymentStatus, $eventType);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Evento já processado - webhook idempotente, retorna como sucesso.
        }
    }

    private function applyToAuthorization(string $paymentId, string $paymentStatus, string $eventType): void
    {
        $authorization = PaymentAuthorization::query()
            ->where('gateway_payment_id', $paymentId)
            ->where('metodo', MetodoPagamento::Pix)
            ->lockForUpdate()
            ->first();

        if ($authorization === null || $authorization->status !== StatusPaymentAuthorization::Pendente) {
            return;
        }

        if (in_array($paymentStatus, self::STATUS_CONFIRMADO, true)) {
            ($this->recordEvent)($authorization, TipoPaymentEvent::Capturado, [
                'motivo' => 'WEBHOOK_ASAAS_CONFIRMADO',
                'gateway_event' => $eventType,
                'gateway_payment_id' => $paymentId,
            ]);

            return;
        }

        if (in_array($paymentStatus, self::STATUS_NAO_VAI_ACONTECER, true) || in_array($eventType, self::EVENTOS_NAO_VAI_ACONTECER, true)) {
            ($this->recordEvent)($authorization, TipoPaymentEvent::Cancelado, [
                'motivo' => 'WEBHOOK_ASAAS_'.($eventType !== '' ? $eventType : $paymentStatus),
                'gateway_payment_id' => $paymentId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): string
    {
        if (isset($payload['id']) && is_string($payload['id']) && $payload['id'] !== '') {
            return $payload['id'];
        }

        // Asaas nem sempre manda um id de evento estável; sem ele, dedupe
        // por hash do corpo inteiro - detecta reentrega exata (o caso
        // normal de retry), não estados diferentes do mesmo pagamento.
        return 'sha256:'.hash('sha256', json_encode($payload) ?: '');
    }
}
