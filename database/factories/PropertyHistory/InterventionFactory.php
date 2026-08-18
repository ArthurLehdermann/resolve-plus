<?php

namespace Database\Factories\PropertyHistory;

use App\PropertyHistory\Asset;
use App\PropertyHistory\Intervention;
use App\PropertyHistory\OrigemIntervention;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'servico_id' => null,
            'data' => fake()->dateTimeBetween('-2 years'),
            'categoria' => fake()->randomElement(['hidraulica', 'eletrica', 'pintura', 'pequenos_reparos', 'montagem']),
            'resumo' => fake()->sentence(),
            'origem' => OrigemIntervention::Manual,
        ];
    }

    public function plataforma(?string $servicoId = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'origem' => OrigemIntervention::Plataforma,
            'servico_id' => $servicoId ?? (string) Str::uuid(),
        ]);
    }

    public function importado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'origem' => OrigemIntervention::Importado,
            'servico_id' => null,
        ]);
    }
}
