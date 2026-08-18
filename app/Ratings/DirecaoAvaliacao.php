<?php

namespace App\Ratings;

enum DirecaoAvaliacao: string
{
    case ClienteAvaliaProfissional = 'CLIENTE_AVALIA_PROFISSIONAL';
    case ProfissionalAvaliaCliente = 'PROFISSIONAL_AVALIA_CLIENTE';
}
