<?php

namespace App\PropertyHistory;

enum OrigemIntervention: string
{
    case Plataforma = 'PLATAFORMA';
    case Manual = 'MANUAL';
    case Importado = 'IMPORTADO';
}
