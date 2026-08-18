<?php

namespace App\Auth\Enums;

enum StatusConta: string
{
    case PendenteVerificacao = 'PENDENTE_VERIFICACAO';
    case Ativa = 'ATIVA';
    case Suspensa = 'SUSPENSA';
    case Bloqueada = 'BLOQUEADA';
    case Excluida = 'EXCLUIDA';
}
