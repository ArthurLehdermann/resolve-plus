<?php

namespace App\Users;

enum NivelConfianca: string
{
    case Verificado = 'VERIFICADO';
    case Bronze = 'BRONZE';
    case Prata = 'PRATA';
    case Ouro = 'OURO';
    case Elite = 'ELITE';
}
