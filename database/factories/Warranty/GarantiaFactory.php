<?php

namespace Database\Factories\Warranty;

use App\Services\Servico;
use App\Services\StatusServico;
use App\Warranty\Garantia;
use App\Warranty\ResponsavelFinanceiro;
use App\Warranty\StatusGarantia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garantia>
 */
class GarantiaFactory extends Factory
{
    protected $model = Garantia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = now();

        return [
            'servico_id' => Servico::factory()->state([
                'status' => StatusServico::Aprovado,
            ]),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addDays(90),
            'status' => StatusGarantia::Ativa,
            'responsavel_financeiro' => ResponsavelFinanceiro::Profissional,
        ];
    }

    public function acionada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusGarantia::Acionada,
        ]);
    }
}
