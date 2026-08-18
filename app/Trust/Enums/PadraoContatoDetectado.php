<?php

namespace App\Trust\Enums;

enum PadraoContatoDetectado: string
{
    case Telefone = 'TELEFONE';
    case Email = 'EMAIL';
    case RedeSocial = 'REDE_SOCIAL';
}
