<?php

namespace App\Payments;

use App\Admin\Configuracao;
use Carbon\CarbonImmutable;

final class CancellationPenalty
{
    /**
     * @return array{percentual: int, valor_centavos: int}
     */
    public function calculate(int $valorProposta, CarbonImmutable $referenciaAgendamento, ?CarbonImmutable $canceladoEm = null): array
    {
        $canceladoEm ??= CarbonImmutable::now();
        $horasRestantes = max(0, $canceladoEm->diffInHours($referenciaAgendamento, false));

        $tier1Hours = Configuracao::inteiro('CANCELLATION_PENALTY_TIER1_HOURS');
        $tier1Percent = Configuracao::inteiro('CANCELLATION_PENALTY_TIER1_PERCENT');
        $tier2Hours = Configuracao::inteiro('CANCELLATION_PENALTY_TIER2_HOURS');
        $tier2Percent = Configuracao::inteiro('CANCELLATION_PENALTY_TIER2_PERCENT');
        $tier3Percent = Configuracao::inteiro('CANCELLATION_PENALTY_TIER3_PERCENT');

        $percentual = match (true) {
            $horasRestantes >= $tier1Hours => $tier1Percent,
            $horasRestantes >= $tier2Hours => $tier2Percent,
            default => $tier3Percent,
        };

        $valorCentavos = (int) floor($valorProposta * $percentual / 100);

        return [
            'percentual' => $percentual,
            'valor_centavos' => $valorCentavos,
        ];
    }
}
