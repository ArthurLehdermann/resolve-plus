<?php

namespace Database\Factories\Payments;

use App\Payments\Servico;
use App\Payments\StatusServico;
use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servico>
 */
class ServicoFactory extends Factory
{
    protected $model = Servico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposta_id' => Proposta::factory()->aceita()->state([
                'solicitacao_id' => Solicitacao::factory()->contratada(),
            ]),
            'status' => StatusServico::AguardandoAprovacao,
            'asaas_wallet_id' => 'wal_'.fake()->uuid(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Servico $servico): void {
            $servico->loadMissing('proposta.solicitacao');
            $updates = [];

            if ($servico->cliente_id === null) {
                $updates['cliente_id'] = $servico->proposta->solicitacao->cliente_id;
            } elseif ($servico->cliente_id !== $servico->proposta->solicitacao->cliente_id) {
                $servico->proposta->solicitacao->forceFill([
                    'cliente_id' => $servico->cliente_id,
                ])->save();
            }

            if ($servico->profissional_id === null) {
                $updates['profissional_id'] = $servico->proposta->profissional_id;
            } elseif ($servico->profissional_id !== $servico->proposta->profissional_id) {
                $servico->proposta->forceFill([
                    'profissional_id' => $servico->profissional_id,
                ])->save();
            }

            if ($updates !== []) {
                $servico->forceFill($updates)->save();
            }
        });
    }

    public function aprovado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::Aprovado,
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::Cancelado,
        ]);
    }

    public function agendado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::Agendado,
        ]);
    }
}
