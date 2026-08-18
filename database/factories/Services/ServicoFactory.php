<?php

namespace Database\Factories\Services;

use App\Proposals\Proposta;
use App\Requests\Solicitacao;
use App\Services\Servico;
use App\Services\StatusServico;
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
            'inicio' => null,
            'fim' => null,
            'notas' => null,
            'fotos' => null,
            'status' => StatusServico::Agendado,
        ];
    }

    public function emAndamento(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::EmAndamento,
            'inicio' => now(),
        ]);
    }

    public function aguardandoAprovacao(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::AguardandoAprovacao,
            'inicio' => now()->subHour(),
            'fim' => now(),
        ]);
    }

    public function aprovado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusServico::Aprovado,
            'inicio' => now()->subHours(2),
            'fim' => now()->subHour(),
        ]);
    }
}
