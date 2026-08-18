<?php

namespace App\PropertyHistory;

enum StatusPropertyOwnershipTransfer: string
{
    case Pendente = 'PENDENTE';
    case Aceito = 'ACEITO';
    case Recusado = 'RECUSADO';
    case Expirado = 'EXPIRADO';
}
