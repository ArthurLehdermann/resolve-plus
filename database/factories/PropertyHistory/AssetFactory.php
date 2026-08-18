<?php

namespace Database\Factories\PropertyHistory;

use App\PropertyHistory\Area;
use App\PropertyHistory\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'nome' => fake()->randomElement(['Torneira', 'Disjuntor', 'Porta', 'Tomada']),
            'tipo' => fake()->optional()->randomElement(['HIDRAULICA', 'ELETRICA', 'ESQUADRIAS']),
        ];
    }

    public function unspecified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'nome' => Asset::FALLBACK_NAME,
            'tipo' => null,
        ]);
    }
}
