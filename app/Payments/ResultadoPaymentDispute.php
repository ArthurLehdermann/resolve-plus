<?php

namespace App\Payments;

enum ResultadoPaymentDispute: string
{
    case Aprovado = 'APROVADO';
    case Cancelado = 'CANCELADO';
}
