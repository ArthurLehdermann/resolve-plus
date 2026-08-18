<?php

namespace Database\Factories\Services;

use App\Services\Agenda;
use App\Services\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agenda>
 */
class AgendaFactory extends Factory
{
    protected $model = Agenda::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'servico_id' => Servico::factory(),
            'data' => fake()->dateTimeBetween('+1 day', '+14 days')->format('Y-m-d'),
            'hora' => fake()->time('H:i:s'),
            'observacoes' => null,
        ];
    }
}
