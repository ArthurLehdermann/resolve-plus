<?php

namespace Database\Factories\PropertyHistory;

use App\Auth\Models\Usuario;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cep' => fake()->unique()->numerify('########'),
            'logradouro' => fake()->streetName(),
            'numero' => (string) fake()->numberBetween(1, 9999),
            'complemento' => fake()->optional()->bothify('APTO###'),
            'bairro' => fake()->word(),
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'latitude' => fake()->optional()->latitude(-33.0, -22.0),
            'longitude' => fake()->optional()->longitude(-53.0, -46.0),
            'apelido' => fake()->optional()->word(),
        ];
    }

    public function ownedBy(Usuario $usuario): static
    {
        return $this->afterCreating(function (Property $property) use ($usuario): void {
            PropertyOwnership::query()->create([
                'property_id' => $property->id,
                'cliente_id' => $usuario->id,
                'desde' => now(),
                'ate' => null,
            ]);
        });
    }
}
