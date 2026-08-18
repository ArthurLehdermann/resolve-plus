<?php

namespace Tests\Feature\Payments;

use App\Payments\Gateway\FakePaymentGateway;
use App\Payments\Jobs\ReauthorizeExpiringPaymentsJob;
use App\Payments\PaymentAuthorization;
use App\Payments\ReauthorizeExpiringPayments;
use App\Payments\RecordPaymentEvent;
use App\Payments\Servico;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusServico;
use App\Payments\TipoPaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Tests\TestCase;

class PaymentInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inv_042_and_inv_046_job_expires_and_reauthorizes_before_expiry(): void
    {
        $servico = Servico::factory()->agendado()->create();
        $authorization = PaymentAuthorization::factory()->expirando()->create([
            'servico_id' => $servico->id,
        ]);

        Artisan::call('payments:reauthorize');

        $authorization->refresh();
        $this->assertSame(StatusPaymentAuthorization::Expirado, $authorization->status);
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Expirado));
        $this->assertTrue($authorization->status->isTerminal());

        $nova = PaymentAuthorization::query()
            ->where('servico_id', $servico->id)
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->first();

        $this->assertNotNull($nova);
        $this->assertNotSame($authorization->id, $nova->id);
        $this->assertTrue($nova->hasEvent(TipoPaymentEvent::Reautorizado));
        $this->assertTrue($nova->hasEvent(TipoPaymentEvent::Autorizado));
        $this->assertSame($authorization->id, $nova->events()
            ->where('tipo', TipoPaymentEvent::Reautorizado)
            ->first()
            ?->payload['autorizacao_anterior_id'] ?? null);

        $this->assertSame(1, PaymentAuthorization::query()
            ->where('servico_id', $servico->id)
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->count());

        $gateway = app(FakePaymentGateway::class);
        $this->assertContains($authorization->gateway_payment_id, $gateway->cancels);
        $this->assertCount(1, $gateway->charges);
    }

    public function test_inv_046_does_not_reauthorize_pix(): void
    {
        $pixServico = Servico::factory()->agendado()->create();
        $pix = PaymentAuthorization::factory()->pixCapturado()->create([
            'servico_id' => $pixServico->id,
        ]);

        (new ReauthorizeExpiringPaymentsJob)->handle(app(ReauthorizeExpiringPayments::class));

        $this->assertSame(StatusPaymentAuthorization::Capturado, $pix->fresh()->status);
        $this->assertSame(1, PaymentAuthorization::query()->where('servico_id', $pixServico->id)->count());
    }

    public function test_inv_042_job_captures_if_service_already_approved(): void
    {
        $aprovado = Servico::factory()->aprovado()->create();
        $card = PaymentAuthorization::factory()->expirada()->create([
            'servico_id' => $aprovado->id,
        ]);

        (new ReauthorizeExpiringPaymentsJob)->handle(app(ReauthorizeExpiringPayments::class));

        $this->assertSame(StatusPaymentAuthorization::Capturado, $card->fresh()->status);
        $this->assertSame(1, PaymentAuthorization::query()->where('servico_id', $aprovado->id)->count());
    }

    public function test_inv_042_cancel_is_a_terminal_status(): void
    {
        $authorization = PaymentAuthorization::factory()->create();

        app(RecordPaymentEvent::class)($authorization, TipoPaymentEvent::Cancelado, [
            'motivo' => 'TESTE',
        ]);
        $authorization->refresh();

        $this->assertSame(StatusPaymentAuthorization::Cancelado, $authorization->status);
        $this->assertTrue($authorization->status->isTerminal());
        $this->assertTrue($authorization->hasEvent(TipoPaymentEvent::Cancelado));
    }

    public function test_partial_unique_index_allows_only_one_autorizado_per_servico(): void
    {
        $servico = Servico::factory()->create();
        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);

        $this->expectException(QueryException::class);

        PaymentAuthorization::factory()->create(['servico_id' => $servico->id]);
    }

    public function test_inv_040_payment_event_is_append_only(): void
    {
        $authorization = PaymentAuthorization::factory()->create();
        $event = $authorization->events()->first();

        $this->assertNotNull($event);
        $this->expectException(LogicException::class);
        $event->update(['payload' => ['mutado' => true]]);
    }

    public function test_inv_040_payment_event_cannot_be_deleted(): void
    {
        $authorization = PaymentAuthorization::factory()->create();
        $event = $authorization->events()->first();

        $this->assertNotNull($event);
        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_job_does_not_leave_autorizado_in_limbo_after_reauth(): void
    {
        $servico = Servico::factory()->create(['status' => StatusServico::EmAndamento]);
        $old = PaymentAuthorization::factory()->expirada()->create([
            'servico_id' => $servico->id,
            'valor' => 5_000,
        ]);

        Artisan::call('payments:reauthorize');

        $this->assertSame(StatusPaymentAuthorization::Expirado, $old->fresh()->status);
        $this->assertTrue(
            PaymentAuthorization::query()
                ->where('servico_id', $servico->id)
                ->where('status', StatusPaymentAuthorization::Autorizado)
                ->exists(),
        );
    }
}
