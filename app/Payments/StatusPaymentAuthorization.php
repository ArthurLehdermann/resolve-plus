<?php

namespace App\Payments;

enum StatusPaymentAuthorization: string
{
    case Autorizado = 'AUTORIZADO';
    case Pendente = 'PENDENTE';
    case Capturado = 'CAPTURADO';
    case Cancelado = 'CANCELADO';
    case Expirado = 'EXPIRADO';

    public function isTerminal(): bool
    {
        return $this !== self::Autorizado && $this !== self::Pendente;
    }
}
