<?php

namespace App\Payments;

enum TipoPaymentDispute: string
{
    case ContestacaoConclusao = 'CONTESTACAO_CONCLUSAO';
    case CancelamentoExecucao = 'CANCELAMENTO_EXECUCAO';
}
