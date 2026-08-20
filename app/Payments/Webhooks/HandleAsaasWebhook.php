<?php

namespace App\Payments\Webhooks;

use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\RecordPaymentEvent;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
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

    private const EVENTOS_CHARGEBACK_CARTAO = [
        'PAYMENT_CHARGEBACK_REQUESTED',
        'PAYMENT_CHARGEBACK_DISPUTE',
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
    ];

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
            ->lockForUpdate()
            ->first();

        if ($authorization === null) {
            return;
        }

        if ($authorization->metodo === MetodoPagamento::Cartao) {
            $this->applyToCardAuthorization($authorization, $eventType);

            return;
        }

        if ($authorization->status !== StatusPaymentAuthorization::Pendente) {
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
     * Chargeback de cartão não muda o status da autorização (a captura
     * aconteceu de fato; o chargeback é um evento de risco sobre ela, não
     * uma correção de estado). O que importa é abrir uma disputa: INV-045
     * já bloqueia captura (CapturePaymentJob) e repasse (ReleasePayment)
     * enquanto existir disputa ABERTA para o serviço, então isso sozinho
     * impede a plataforma de repassar dinheiro que o Asaas está revertendo
     * (N6). A resolução em si ainda é manual - ResolveDispute não sabe
     * automatizar o desfecho de um chargeback.
     */
    private function applyToCardAuthorization(PaymentAuthorization $authorization, string $eventType): void
    {
        if (! in_array($eventType, self::EVENTOS_CHARGEBACK_CARTAO, true)) {
            return;
        }

        $jaAberta = PaymentDispute::query()
            ->where('servico_id', $authorization->servico_id)
            ->where('tipo', TipoPaymentDispute::Chargeback)
            ->where('status', StatusPaymentDispute::Aberta)
            ->exists();

        if ($jaAberta) {
            return;
        }

        try {
            // Transação aninhada = SAVEPOINT: se colidir com o índice único
            // (corrida entre duas entregas do mesmo chargeback), o rollback
            // fica restrito ao savepoint em vez de abortar a transação
            // inteira do webhook (Postgres marca a conexão como 25P02 até
            // um ROLLBACK/ROLLBACK TO SAVEPOINT de verdade acontecer -
            // capturar a exceção em PHP sozinho não limpa isso).
            DB::transaction(function () use ($authorization, $eventType): void {
                PaymentDispute::query()->create([
                    'servico_id' => $authorization->servico_id,
                    'tipo' => TipoPaymentDispute::Chargeback,
                    'status' => StatusPaymentDispute::Aberta,
                    'motivo' => 'Chargeback reportado pelo Asaas ('.$eventType.').',
                    'aberta_em' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Já existe disputa de chargeback ABERTA para este serviço - idempotente.
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
