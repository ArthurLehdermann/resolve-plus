<?php

namespace Database\Factories\Requests;

use App\Auth\Models\Usuario;
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
        return [
            'cliente_id' => Usuario::factory(),
            'status' => StatusSolicitacao::Aberta,
        ];
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
