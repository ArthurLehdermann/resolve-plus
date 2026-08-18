<?php

namespace App\Users;

/**
 * Progressão de Nível de Confiança (D4 / issue #4, `foundation/05-trust-level.md`).
 */
final class CalcularNivelConfianca
{
    /**
     * @var list<NivelConfianca>
     */
    private const ORDEM_DESC = [
        NivelConfianca::Elite,
        NivelConfianca::Ouro,
        NivelConfianca::Prata,
        NivelConfianca::Bronze,
    ];

    public function __invoke(
        int $servicosAprovados,
        ?int $notaMediaDez,
        int $taxaCancelamentoPct,
        int $diasConta,
        int $reclamacoes12m,
    ): NivelConfianca {
        if ($notaMediaDez === null) {
            return NivelConfianca::Verificado;
        }

        foreach (self::ORDEM_DESC as $nivel) {
            if ($this->atende(
                $nivel,
                $servicosAprovados,
                $notaMediaDez,
                $taxaCancelamentoPct,
                $diasConta,
                $reclamacoes12m,
            )) {
                return $nivel;
            }
        }

        return NivelConfianca::Verificado;
    }

    private function atende(
        NivelConfianca $nivel,
        int $servicosAprovados,
        int $notaMediaDez,
        int $taxaCancelamentoPct,
        int $diasConta,
        int $reclamacoes12m,
    ): bool {
        return match ($nivel) {
            NivelConfianca::Elite => $servicosAprovados >= 50
                && $notaMediaDez >= 47
                && $taxaCancelamentoPct <= 5
                && $diasConta >= 365
                && $reclamacoes12m <= 0,
            NivelConfianca::Ouro => $servicosAprovados >= 25
                && $notaMediaDez >= 45
                && $taxaCancelamentoPct <= 10
                && $diasConta >= 180
                && $reclamacoes12m <= 0,
            NivelConfianca::Prata => $servicosAprovados >= 10
                && $notaMediaDez >= 43
                && $taxaCancelamentoPct <= 15
                && $diasConta >= 90
                && $reclamacoes12m <= 0,
            NivelConfianca::Bronze => $servicosAprovados >= 3
                && $notaMediaDez >= 40
                && $taxaCancelamentoPct <= 20
                && $diasConta >= 30
                && $reclamacoes12m <= 1,
            NivelConfianca::Verificado => true,
        };
    }
}
