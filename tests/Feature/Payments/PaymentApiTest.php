<?php

namespace Tests\Feature\Payments;

use App\Auth\Models\Usuario;
use App\Payments\CapturePayment;
use App\Payments\CommissionRate;
use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDispute;
use App\Payments\RecordPaymentEvent;
use App\Payments\Servico;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusServico;
use App\Payments\TipoPaymentEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_payments_lists_authorizations_of_the_cliente(): void
    {
        $cliente = Usuario::factory()->create();
        $outro = Usuario::factory()->create();
        $servico = Servico::factory()->create(['cliente_id' => $cliente->id]);
        $authorization = PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);
        PaymentAuthorization::factory()->create([
            'servico_id' => Servico::factory()->create(['cliente_id' => $outro->id])->id,
        ]);

        $this->withToken($cliente->createToken('auth')->plainTextToken)
            ->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $authorization->id);
    }

    public function test_get_payment_and_events_return_append_only_history(): void
    {
        $cliente = Usuario::factory()->create();
        $servico = Servico::factory()->create(['cliente_id' => $cliente->id]);
        $authorization = PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);
        $token = $cliente->createToken('auth')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$authorization->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'AUTORIZADO')
            ->assertJsonPath('data.metodo', 'CARTAO')
            ->assertJsonPath('data.valor', 10_000);

        $this->withToken($token)
            ->getJson("/api/v1/payments/{$authorization->id}/events")
            ->assertOk()
            ->assertJsonPath('data.0.tipo', 'AUTORIZADO');
    }

    public function test_approve_service_captures_card_via_gateway(): void
    {
        $cliente = Usuario::factory()->create();
        $servico = Servico::factory()->create([
            'cliente_id' => $cliente->id,
            'status' => StatusServico::AguardandoAprovacao,
        ]);
        $authorization = PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $this->withToken($cliente->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APROVADO');

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));
        $this->assertNotNull($authorization->captureEvent()?->split);

        $gateway = app(FakePaymentGateway::class);
        $this->assertNotEmpty($gateway->captures);
        $this->assertSame($authorization->gateway_payment_id, $gateway->captures[0]['id']);
    }

    public function test_approve_is_idempotent_with_the_same_key(): void
    {
        $cliente = Usuario::factory()->create();
        $servico = Servico::factory()->create([
            'cliente_id' => $cliente->id,
            'status' => StatusServico::AguardandoAprovacao,
        ]);
        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $key = (string) Str::uuid();
        $token = $cliente->createToken('auth')->plainTextToken;

        $first = $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertOk();

        $this->withToken($token)
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertOk()
            ->assertExactJson($first->json());

        $this->assertCount(1, app(FakePaymentGateway::class)->captures);
    }

    public function test_approve_requires_idempotency_key_and_cliente(): void
    {
        $cliente = Usuario::factory()->create();
        $outro = Usuario::factory()->create();
        $servico = Servico::factory()->create(['cliente_id' => $cliente->id]);
        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $this->withToken($cliente->createToken('auth')->plainTextToken)
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertUnprocessable();

        $this->flushHeaders();

        $this->actingAs($outro, 'sanctum')
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/services/{$servico->id}/approve")
            ->assertForbidden();
    }

    public function test_admin_release_records_audit_and_repassado(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $cliente = Usuario::factory()->create();
        $servico = Servico::factory()->aprovado()->create(['cliente_id' => $cliente->id]);
        $authorization = PaymentAuthorization::factory()->capturado()->create(['servico_id' => $servico->id]);

        $this->withToken($admin->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/payments/{$authorization->id}/release", [
                'justificativa' => 'Liberação manual após mediação do caso.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'CAPTURADO');

        $authorization->refresh();
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Repassado));
        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $admin->id,
            'acao' => 'payments.release',
            'id_entidade' => $authorization->id,
        ]);
    }

    public function test_admin_release_of_pix_transfers_via_gateway(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $servico = Servico::factory()->aprovado()->create([
            'asaas_wallet_id' => 'wal_profissional',
        ]);
        $authorization = PaymentAuthorization::factory()->pixCapturado()->create([
            'servico_id' => $servico->id,
        ]);

        $this->withToken($admin->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/payments/{$authorization->id}/release", [
                'justificativa' => 'Repasse Pix após janela de contestação.',
            ])
            ->assertOk();

        $this->assertSame(
            [['walletId' => 'wal_profissional', 'amount' => 9000]],
            app(FakePaymentGateway::class)->transfers,
        );
    }

    public function test_release_rejects_non_admin_and_missing_justificativa(): void
    {
        $cliente = Usuario::factory()->create();
        $servico = Servico::factory()->aprovado()->create(['cliente_id' => $cliente->id]);
        $authorization = PaymentAuthorization::factory()->capturado()->create(['servico_id' => $servico->id]);

        $this->withToken($cliente->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/payments/{$authorization->id}/release", [
                'justificativa' => 'Justificativa longa o suficiente.',
            ])
            ->assertForbidden();

        $admin = Usuario::factory()->admin()->create();

        $this->withToken($admin->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/payments/{$authorization->id}/release", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['justificativa']);
    }

    public function test_inv_044_split_uses_commission_rate_at_capture_time(): void
    {
        $commission = app(CommissionRate::class);
        $commission->set(10);

        $authorization = PaymentAuthorization::factory()->create(['valor' => 10_000]);
        app(CapturePayment::class)($authorization);
        $split = $authorization->refresh()->captureEvent()?->split;

        $this->assertNotNull($split);
        $this->assertEqualsWithDelta(10.0, (float) $split->aliquota_vigente, 0.0001);
        $this->assertSame(1_000, $split->valor_plataforma);
        $this->assertSame(9_000, $split->valor_profissional);

        $commission->set(25);
        $split->refresh();
        $this->assertEqualsWithDelta(10.0, (float) $split->aliquota_vigente, 0.0001);
        $this->assertSame(1_000, $split->valor_plataforma);

        $outro = PaymentAuthorization::factory()->create(['valor' => 10_000]);
        app(CapturePayment::class)($outro);
        $novoSplit = $outro->refresh()->captureEvent()?->split;

        $this->assertNotNull($novoSplit);
        $this->assertEqualsWithDelta(25.0, (float) $novoSplit->aliquota_vigente, 0.0001);
        $this->assertSame(2_500, $novoSplit->valor_plataforma);
        $this->assertSame(7_500, $novoSplit->valor_profissional);
    }

    public function test_inv_045_dispute_blocks_release_but_not_other_events(): void
    {
        $admin = Usuario::factory()->admin()->create();
        $servico = Servico::factory()->create();
        $authorization = PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        PaymentDispute::factory()->create(['servico_id' => $servico->id]);

        app(CapturePayment::class)($authorization);
        $authorization->refresh();
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Capturado));
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->status);

        $this->withToken($admin->createToken('auth')->plainTextToken)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/payments/{$authorization->id}/release", [
                'justificativa' => 'Tentativa de repasse com disputa aberta.',
            ])
            ->assertConflict();

        $this->assertFalse($authorization->fresh()->hasEvent(TipoPaymentEvent::Repassado));

        app(RecordPaymentEvent::class)($authorization, TipoPaymentEvent::Reembolsado, [
            'valor' => 100,
        ]);
        $this->assertTrue($authorization->fresh()->hasEvent(TipoPaymentEvent::Reembolsado));
        $this->assertSame(StatusPaymentAuthorization::Capturado, $authorization->fresh()->status);
        $this->assertSame(MetodoPagamento::Cartao, $authorization->fresh()->metodo);
    }

    public function test_unauthenticated_payments_are_rejected(): void
    {
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }
}
