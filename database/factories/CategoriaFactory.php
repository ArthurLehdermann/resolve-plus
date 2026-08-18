<?php

namespace Database\Factories;

use App\Categories\Models\Categoria;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

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

    public function mvp(string $codigo): static
    {
        $definition = collect(CategoriaSeeder::definitions())
            ->firstWhere('codigo', $codigo);

        if (! is_array($definition)) {
            throw new InvalidArgumentException('Categoria MVP desconhecida: '.$codigo);
        }

        return $this->state(fn (array $attributes): array => [
            'codigo' => $definition['codigo'],
            'nome' => $definition['nome'],
            'descricao' => $definition['descricao'],
            'ativo' => $definition['ativo'],
            'template_escopo' => $definition['template_escopo'],
        ]);
    }
}
