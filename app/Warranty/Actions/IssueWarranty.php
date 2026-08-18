<?php

namespace App\Warranty\Actions;

use App\Services\Servico;
use App\Warranty\Garantia;
use App\Warranty\ResponsavelFinanceiro;
use App\Warranty\StatusGarantia;
use Illuminate\Support\Facades\DB;

class IssueWarranty
{
    public function __invoke(Servico $servico): Garantia
    {
        return DB::transaction(function () use ($servico): Garantia {
            $servico = Servico::query()
                ->whereKey($servico->id)
                ->lockForUpdate()
                ->with('proposta')
                ->firstOrFail();

            $existing = Garantia::query()->where('servico_id', $servico->id)->first();
            if ($existing !== null) {
                return $existing;
            }

            $garantiaDias = (int) $servico->proposta->garantia_dias;
            $inicio = now();

            return Garantia::query()->create([
                'servico_id' => $servico->id,
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addDays($garantiaDias),
                'status' => StatusGarantia::Ativa,
                'responsavel_financeiro' => ResponsavelFinanceiro::Profissional,
            ]);
        });
    }
}
