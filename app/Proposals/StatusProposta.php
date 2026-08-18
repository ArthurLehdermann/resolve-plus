<?php

namespace App\Proposals;

enum StatusProposta: string
{
    case Enviada = 'ENVIADA';
    case Aceita = 'ACEITA';
    case Recusada = 'RECUSADA';
    case Retirada = 'RETIRADA';
}
