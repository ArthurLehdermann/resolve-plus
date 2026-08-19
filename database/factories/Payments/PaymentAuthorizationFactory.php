<?php

namespace Database\Factories\Payments;

use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentEvent;
use App\Payments\PaymentSplit;
use App\Payments\SplitCalculator;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\TipoPaymentEvent;
use App\Services\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAuthorization>
 */
class PaymentAuthorizationFactory extends Factory
{
    protected $model = PaymentAuthorization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servico_id' => Servico::factory(),
            'valor' => 10000,
            'metodo' => MetodoPagamento::Cartao,
            'status' => StatusPaymentAuthorization::Autorizado,
            'gateway_payment_id' => 'pay_'.Str::uuid(),
            'credit_card_token' => 'tok_'.Str::uuid(),
            'gateway_customer_id' => 'cus_'.Str::uuid(),
            'expira_em' => now()->addDays(3),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PaymentAuthorization $authorization): void {
            if ($authorization->events()->exists()) {
                return;
            }

            $tipo = $authorization->status === StatusPaymentAuthorization::Capturado
                ? TipoPaymentEvent::Capturado
                : TipoPaymentEvent::Autorizado;

            $event = PaymentEvent::query()->create([
                'payment_authorization_id' => $authorization->id,
                'tipo' => $tipo,
                'payload' => [
                    'gateway_payment_id' => $authorization->gateway_payment_id,
                    'origem' => 'factory',
                ],
            ]);

            if ($tipo === TipoPaymentEvent::Capturado && $event->split()->doesntExist()) {
                $split = (new SplitCalculator)->calculate(
                    $authorization->valor,
                    (float) config('payments.default_commission_percent'),
                );

                PaymentSplit::query()->create([
                    'payment_event_id' => $event->id,
                    ...$split,
                ]);
            }
        });
    }

    public function pixCapturado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'metodo' => MetodoPagamento::Pix,
            'status' => StatusPaymentAuthorization::Capturado,
            'credit_card_token' => null,
            'expira_em' => null,
        ]);
    }

    public function capturado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPaymentAuthorization::Capturado,
            'expira_em' => now()->subDay(),
        ]);
    }

    public function expirando(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPaymentAuthorization::Autorizado,
            'metodo' => MetodoPagamento::Cartao,
            'expira_em' => now()->addHours(2),
        ]);
    }

    public function expirada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPaymentAuthorization::Autorizado,
            'metodo' => MetodoPagamento::Cartao,
            'expira_em' => now()->subHour(),
        ]);
    }
}
