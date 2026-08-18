<?php

namespace App\Auth\Enums;

enum TipoUsuario: string
{
    case Cliente = 'CLIENTE';
    case Profissional = 'PROFISSIONAL';
    case Admin = 'ADMIN';
}
