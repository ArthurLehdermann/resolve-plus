<?php

namespace App\Payments;

enum StatusServico: string
{
    case Agendado = 'AGENDADO';
    case EmAndamento = 'EM_ANDAMENTO';
    case AguardandoAprovacao = 'AGUARDANDO_APROVACAO';
    case Aprovado = 'APROVADO';
    case EmContestacao = 'EM_CONTESTACAO';
    case Cancelado = 'CANCELADO';
}
