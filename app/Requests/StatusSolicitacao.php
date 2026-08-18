<?php

namespace App\Requests;

enum StatusSolicitacao: string
{
    case Criada = 'CRIADA';
    case Aberta = 'ABERTA';
    case RecebendoPropostas = 'RECEBENDO_PROPOSTAS';
    case Contratada = 'CONTRATADA';
    case Cancelada = 'CANCELADA';
    case Expirada = 'EXPIRADA';

    public function aceitaPropostas(): bool
    {
        return $this === self::Aberta || $this === self::RecebendoPropostas;
    }
}
