<?php

namespace Database\Factories\Users;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Users\NivelConfianca;
use App\Users\PerfilProfissional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerfilProfissional>
 */
class PerfilProfissionalFactory extends Factory
{
    protected $model = PerfilProfissional::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory()->state([
                'tipo' => TipoUsuario::Profissional,
                'status' => StatusConta::Ativa,
            ]),
            'categorias_atendidas' => null,
            'nivel_confianca' => NivelConfianca::Verificado,
            'servicos_aprovados' => 0,
            'nota_media_dez' => null,
            'taxa_cancelamento_pct' => 0,
            'reclamacoes_12m' => 0,
            'nivel_atualizado_em' => now(),
        ];
    }
}
