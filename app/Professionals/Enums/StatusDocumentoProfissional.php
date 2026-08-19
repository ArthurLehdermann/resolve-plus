<?php

namespace App\Professionals\Enums;

enum StatusDocumentoProfissional: string
{
    case Pendente = 'PENDENTE';
    case Aprovado = 'APROVADO';
    case Rejeitado = 'REJEITADO';
    case Vencido = 'VENCIDO';
}
