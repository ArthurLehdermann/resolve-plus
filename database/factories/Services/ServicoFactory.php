<?php

namespace Database\Factories\Services;

use App\Proposals\Proposta;
use App\Services\Servico;
use App\Services\StatusServico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servico>
 */
class ServicoFactory extends Factory
{
    protected $model = Servico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proposta_id' => Proposta::factory(),
            'inicio' => null,
            'fim' => null,
            'status' => StatusServico::Agendado,
        ];
    }
}
