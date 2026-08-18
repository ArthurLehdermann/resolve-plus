<?php

namespace Database\Factories\Ratings;

use App\Ratings\Avaliacao;
use App\Ratings\DirecaoAvaliacao;
use App\Services\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avaliacao>
 */
class AvaliacaoFactory extends Factory
{
    protected $model = Avaliacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $servico = Servico::factory()->aprovado()->create();

        return [
            'servico_id' => $servico->id,
            'autor_id' => $servico->clienteId(),
            'alvo_id' => $servico->profissionalId(),
            'direcao' => DirecaoAvaliacao::ClienteAvaliaProfissional,
            'nota' => fake()->numberBetween(1, 5),
            'comentario' => fake()->optional()->sentence(),
        ];
    }

    public function profissionalAvaliaCliente(): static
    {
        return $this->state(function (array $attributes): array {
            $servico = Servico::query()->findOrFail($attributes['servico_id']);

            return [
                'autor_id' => $servico->profissionalId(),
                'alvo_id' => $servico->clienteId(),
                'direcao' => DirecaoAvaliacao::ProfissionalAvaliaCliente,
            ];
        });
    }
}
