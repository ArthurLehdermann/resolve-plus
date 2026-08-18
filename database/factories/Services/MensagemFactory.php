<?php

namespace Database\Factories\Services;

use App\Auth\Models\Usuario;
use App\Services\Mensagem;
use App\Services\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mensagem>
 */
class MensagemFactory extends Factory
{
    protected $model = Mensagem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servico_id' => Servico::factory(),
            'remetente_id' => Usuario::factory(),
            'texto' => fake()->sentence(),
            'anexo' => null,
            'enviado_em' => now(),
        ];
    }
}
