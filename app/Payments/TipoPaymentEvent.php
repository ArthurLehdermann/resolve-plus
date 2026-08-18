<?php

namespace App\Payments;

enum TipoPaymentEvent: string
{
    case Autorizado = 'AUTORIZADO';
    case Capturado = 'CAPTURADO';
    case Repassado = 'REPASSADO';
    case Cancelado = 'CANCELADO';
    case Expirado = 'EXPIRADO';
    case Reembolsado = 'REEMBOLSADO';
    case Reautorizado = 'REAUTORIZADO';

    public function projectedStatus(): ?StatusPaymentAuthorization
    {
        return match ($this) {
            self::Autorizado => StatusPaymentAuthorization::Autorizado,
            self::Capturado => StatusPaymentAuthorization::Capturado,
            self::Cancelado => StatusPaymentAuthorization::Cancelado,
            self::Expirado => StatusPaymentAuthorization::Expirado,
            default => null,
        };
    }
}
