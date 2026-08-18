<?php

namespace Database\Factories;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => TipoUsuario::Cliente,
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('11#########'),
            'senha_hash' => fake()->password(12),
            'foto' => null,
            'status' => StatusConta::Ativa,
        ];
    }

    public function profissional(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoUsuario::Profissional,
            'status' => StatusConta::PendenteVerificacao,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => TipoUsuario::Admin,
            'status' => StatusConta::Ativa,
        ]);
    }
}
