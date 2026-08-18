<?php

namespace App\Payments;

enum MetodoPagamento: string
{
    case Cartao = 'CARTAO';
    case Pix = 'PIX';
}
