<?php

namespace Database\Factories;

use App\Categories\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('cat_????'),
            'nome' => fake()->unique()->words(2, true),
            'descricao' => fake()->sentence(),
            'ativo' => true,
            'template_escopo' => [
                'tipo_servico' => [
                    'tipo' => 'enum',
                    'obrigatorio' => true,
                    'rotulo' => 'Tipo de serviço',
                    'valores' => ['OUTRO'],
                ],
            ],
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => [
            'ativo' => false,
        ]);
    }
}
