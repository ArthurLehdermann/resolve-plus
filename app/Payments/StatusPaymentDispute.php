<?php

namespace App\Payments;

enum StatusPaymentDispute: string
{
    case Aberta = 'ABERTA';
    case Resolvida = 'RESOLVIDA';
}
