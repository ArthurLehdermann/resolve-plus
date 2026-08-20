<?php

namespace App\Payments;

enum TipoPaymentDispute: string
{
    case ContestacaoConclusao = 'CONTESTACAO_CONCLUSAO';
    case CancelamentoExecucao = 'CANCELAMENTO_EXECUCAO';

    /**
     * Aberta pelo webhook do Asaas (HandleAsaasWebhook), não pelo usuário -
     * ver OpenDisputeRequest, que não inclui este tipo na whitelist.
     */
    case Chargeback = 'CHARGEBACK';
}
