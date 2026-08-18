<?php

namespace Database\Factories\Payments;

use App\Payments\PaymentDispute;
use App\Payments\Servico;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentDispute>
 */
class PaymentDisputeFactory extends Factory
{
    protected $model = PaymentDispute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servico_id' => Servico::factory(),
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
        ];
    }
}
