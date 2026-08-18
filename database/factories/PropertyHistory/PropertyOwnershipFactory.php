<?php

namespace Database\Factories\PropertyHistory;

use App\Auth\Models\Usuario;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyOwnership>
 */
class PropertyOwnershipFactory extends Factory
{
    protected $model = PropertyOwnership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'cliente_id' => Usuario::factory(),
            'desde' => now(),
            'ate' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ate' => now(),
        ]);
    }
}
