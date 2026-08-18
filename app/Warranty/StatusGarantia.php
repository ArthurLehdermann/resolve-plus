<?php

namespace App\Warranty;

enum StatusGarantia: string
{
    case Ativa = 'ATIVA';
    case Expirada = 'EXPIRADA';
    case Acionada = 'ACIONADA';
    case Encerrada = 'ENCERRADA';
}
