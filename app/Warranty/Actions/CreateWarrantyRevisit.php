<?php

namespace App\Warranty\Actions;

use App\Services\Servico;
use App\Services\StatusServico;
use App\Warranty\Garantia;

/**
 * INV-033: serviço de revisita dentro da garantia, mesma causa/escopo, sem nova cobrança.
 */
class CreateWarrantyRevisit
{
    public function __invoke(Garantia $garantia): Servico
    {
        $existing = Servico::query()
            ->where('garantia_origem_id', $garantia->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Servico::query()->create([
            'proposta_id' => null,
            'garantia_origem_id' => $garantia->id,
            'status' => StatusServico::Agendado,
        ]);
    }
}
