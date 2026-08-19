<?php

namespace Database\Factories\Payments;

use App\Payments\PaymentDispute;
use App\Payments\StatusPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
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
            'servico_id' => Servico::factory()->state([
                'proposta_id' => Proposta::factory()->aceita()->state([
                    'solicitacao_id' => Solicitacao::factory()->contratada(),
                ]),
            ]),
            'tipo' => TipoPaymentDispute::ContestacaoConclusao,
            'status' => StatusPaymentDispute::Aberta,
            'aberta_em' => now(),
        ];
    }
}
