<?php

namespace Tests\Feature\Payments;

use App\Payments\PaymentAuthorization;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use App\Payments\Webhooks\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejeita_sem_token_valido(): void
    {
        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'gateway_payment_id' => 'pay_teste_1',
        ]);

        $this->postJson('/api/v1/webhooks/asaas', $this->payload('PAYMENT_CONFIRMED', 'pay_teste_1', 'CONFIRMED'))
            ->assertStatus(401);

        $this->withHeaders(['asaas-access-token' => 'token-errado'])
            ->postJson('/api/v1/webhooks/asaas', $this->payload('PAYMENT_CONFIRMED', 'pay_teste_1', 'CONFIRMED'))
            ->assertStatus(401);

        $this->assertSame(StatusPaymentAuthorization::Pendente, $authorization->fresh()->status);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
    }

    public function test_webhook_confirma_pix_pendente(): void
    {
        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'gateway_payment_id' => 'pay_teste_2',
        ]);

        $this->withHeaders(['asaas-access-token' => 'test-webhook-token'])
            ->postJson('/api/v1/webhooks/asaas', $this->payload('PAYMENT_CONFIRMED', 'pay_teste_2', 'CONFIRMED', 'evt_1'))
            ->assertOk()
            ->assertJsonPath('data.recebido', true);

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
    }

    public function test_webhook_e_idempotente_por_event_id(): void
    {
        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'gateway_payment_id' => 'pay_teste_3',
        ]);
        $payload = $this->payload('PAYMENT_CONFIRMED', 'pay_teste_3', 'CONFIRMED', 'evt_repetido');

        $this->withHeaders(['asaas-access-token' => 'test-webhook-token'])
            ->postJson('/api/v1/webhooks/asaas', $payload)
            ->assertOk();

        // Asaas reentrega o mesmo evento até receber 2xx - a segunda
        // chamada não pode gerar um segundo PaymentEvent CAPTURADO.
        $this->withHeaders(['asaas-access-token' => 'test-webhook-token'])
            ->postJson('/api/v1/webhooks/asaas', $payload)
            ->assertOk();

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(
            1,
            $authorization->events()->where('tipo', TipoPaymentEvent::Capturado)->count(),
        );
    }

    public function test_webhook_cancela_pix_pendente_quando_pagamento_nao_vai_acontecer(): void
    {
        $authorization = PaymentAuthorization::factory()->pixPendente()->create([
            'gateway_payment_id' => 'pay_teste_4',
        ]);

        $this->withHeaders(['asaas-access-token' => 'test-webhook-token'])
            ->postJson('/api/v1/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'pay_teste_4', 'OVERDUE', 'evt_2'))
            ->assertOk();

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Cancelado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Cancelado));
    }

    public function test_webhook_ignora_pagamento_de_autorizacao_ja_capturada(): void
    {
        $authorization = PaymentAuthorization::factory()->pixCapturado()->create([
            'gateway_payment_id' => 'pay_teste_5',
        ]);
        $eventosAntes = $authorization->events()->count();

        $this->withHeaders(['asaas-access-token' => 'test-webhook-token'])
            ->postJson('/api/v1/webhooks/asaas', $this->payload('PAYMENT_CONFIRMED', 'pay_teste_5', 'CONFIRMED', 'evt_3'))
            ->assertOk();

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertSame($eventosAntes, $authorization->events()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $event, string $paymentId, string $paymentStatus, ?string $eventId = null): array
    {
        return array_filter([
            'id' => $eventId,
            'event' => $event,
            'payment' => [
                'id' => $paymentId,
                'status' => $paymentStatus,
            ],
        ], fn ($v) => $v !== null);
    }
}
