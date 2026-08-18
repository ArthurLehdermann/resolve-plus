<?php

namespace Database\Factories\PropertyHistory;

use App\PropertyHistory\Area;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'property_id' => (string) Str::uuid(),
            'nome' => fake()->randomElement(['Cozinha', 'Banheiro', 'Sala', 'Área de serviço']),
        ];
    }

    public function unspecified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'nome' => Area::FALLBACK_NAME,
        ]);
    }
}
