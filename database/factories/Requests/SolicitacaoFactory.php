<?php

namespace Database\Factories\Requests;

use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;
use App\PropertyHistory\Property;
use App\Requests\Solicitacao;
use App\Requests\StatusSolicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Solicitacao>
 */
class SolicitacaoFactory extends Factory
{
    protected $model = Solicitacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cliente = Usuario::factory();

        return [
            'cliente_id' => $cliente,
            'categoria_id' => Categoria::factory(),
            'property_id' => Property::factory(),
            'descricao' => fake()->sentence(),
            'escopo' => [
                'tipo_servico' => 'OUTRO',
            ],
            'status' => StatusSolicitacao::Aberta,
            'data_desejada' => now()->addDays(3)->toDateString(),
        ];
    }

    public function forCliente(Usuario $usuario): static
    {
        return $this->state(fn (array $attributes): array => [
            'cliente_id' => $usuario->id,
            'property_id' => Property::factory()->ownedBy($usuario),
        ]);
    }

    public function recebendoPropostas(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusSolicitacao::RecebendoPropostas,
        ]);
    }

    public function contratada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusSolicitacao::Contratada,
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusSolicitacao::Cancelada,
        ]);
    }

    public function expirada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusSolicitacao::Expirada,
        ]);
    }
}
