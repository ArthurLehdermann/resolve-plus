<?php

namespace App\Notifications;

/**
 * Os dois lados da jornada. Cada caso corresponde a um evento de domínio que já
 * existia; a notificação é leitura desse evento, não uma regra nova.
 */
enum TipoNotificacao: string
{
    case SolicitacaoNova = 'SOLICITACAO_NOVA';
    case PropostaRecebida = 'PROPOSTA_RECEBIDA';
    case PropostaAceita = 'PROPOSTA_ACEITA';
    case ServicoIniciado = 'SERVICO_INICIADO';
    case ServicoConcluido = 'SERVICO_CONCLUIDO';
    case ServicoAprovado = 'SERVICO_APROVADO';
}
