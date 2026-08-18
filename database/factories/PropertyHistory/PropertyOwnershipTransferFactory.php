<?php

namespace Database\Factories\PropertyHistory;

use App\Auth\Models\Usuario;
use App\PropertyHistory\Property;
use App\PropertyHistory\PropertyOwnershipTransfer;
use App\PropertyHistory\StatusPropertyOwnershipTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyOwnershipTransfer>
 */
class PropertyOwnershipTransferFactory extends Factory
{
    protected $model = PropertyOwnershipTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $destino = Usuario::factory()->create();

        return [
            'property_id' => Property::factory(),
            'de_cliente_id' => Usuario::factory(),
            'para_cliente_id' => $destino->id,
            'para_email' => $destino->email,
            'status' => StatusPropertyOwnershipTransfer::Pendente,
            'expira_em' => now()->addDays(PropertyOwnershipTransfer::EXPIRATION_DAYS),
        ];
    }

    public function pendente(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusPropertyOwnershipTransfer::Pendente,
        ]);
    }
}
