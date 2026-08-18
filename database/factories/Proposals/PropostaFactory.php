<?php

namespace Database\Factories\Proposals;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Proposals\Proposta;
use App\Proposals\StatusProposta;
use App\Requests\Solicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposta>
 */
class PropostaFactory extends Factory
{
    protected $model = Proposta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitacao_id' => Solicitacao::factory(),
            'profissional_id' => Usuario::factory()->state([
                'tipo' => TipoUsuario::Profissional,
                'status' => StatusConta::Ativa,
            ]),
            'valor' => fake()->numberBetween(5_000, 80_000),
            'prazo_dias' => fake()->numberBetween(1, 15),
            'garantia_dias' => fake()->randomElement([30, 60, 90]),
            'observacoes' => null,
            'status' => StatusProposta::Enviada,
        ];
    }
}
