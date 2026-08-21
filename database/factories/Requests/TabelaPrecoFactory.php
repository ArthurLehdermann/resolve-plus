<?php

namespace Database\Factories\Requests;

use App\Categories\Models\Categoria;
use App\Requests\TabelaPreco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabelaPreco>
 */
class TabelaPrecoFactory extends Factory
{
    protected $model = TabelaPreco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'cidade' => 'São Paulo',
            'valor_min' => 8000,
            'valor_max' => 25000,
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ativo' => false,
        ]);
    }
}
