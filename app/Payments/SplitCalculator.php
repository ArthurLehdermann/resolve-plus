<?php

namespace App\Payments;

final class SplitCalculator
{
    /**
     * @return array{valor_profissional: int, valor_plataforma: int, aliquota_vigente: float}
     */
    public function calculate(int $valorCentavos, float $aliquotaPercent): array
    {
        $plataforma = (int) round($valorCentavos * $aliquotaPercent / 100);

        return [
            'valor_plataforma' => $plataforma,
            'valor_profissional' => $valorCentavos - $plataforma,
            'aliquota_vigente' => $aliquotaPercent,
        ];
    }
}
