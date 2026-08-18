<?php

namespace Database\Factories\Requests;

use App\Requests\FotoSolicitacao;
use App\Requests\Solicitacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FotoSolicitacao>
 */
class FotoSolicitacaoFactory extends Factory
{
    protected $model = FotoSolicitacao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'solicitacao_id' => Solicitacao::factory(),
            'url' => 'requests/'.fake()->uuid().'/foto.jpg',
            'ordem' => 0,
        ];
    }
}
