<?php

namespace App\PropertyHistory;

enum ConfiabilidadeIntervention: string
{
    case Alta = 'ALTA';
    case Media = 'MEDIA';
    case Baixa = 'BAIXA';

    public static function fromOrigem(OrigemIntervention $origem): self
    {
        return match ($origem) {
            OrigemIntervention::Plataforma => self::Alta,
            OrigemIntervention::Importado => self::Media,
            OrigemIntervention::Manual => self::Baixa,
        };
    }
}
