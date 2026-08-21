<?php

namespace App\Users\Listeners;

use App\Payments\Events\PaymentDisputeResolvida;
use App\Payments\ResultadoPaymentDispute;
use App\Payments\TipoPaymentDispute;
use App\Users\Jobs\RecalcularPerfilConfiancaJob;

/**
 * `foundation/05-trust-level.md`, "reclamação" item 2: PaymentDispute
 * RESOLVIDA com desfecho desfavorável ao profissional conta como
 * reclamação (`reclamacoes_12m`). Sem este listener, o único gatilho de
 * recálculo era `AvaliacaoRegistrada` - uma disputa resolvida contra o
 * profissional não atualizava o nível de confiança exibido ao cliente.
 *
 * Só cobre `CONTESTACAO_CONCLUSAO` -> `CANCELADO`: é o único desfecho
 * mecanicamente inequívoco (contestação do cliente sobre a conclusão do
 * serviço foi julgada procedente). `CANCELAMENTO_EXECUCAO` fica de fora
 * de propósito - `PaymentDispute` não registra quem pediu o cancelamento
 * (cliente ou profissional), então não dá pra atribuir o desfecho a um
 * dos dois só pelo `resultado`. Precisa de decisão de produto (e
 * provavelmente um campo novo) antes de automatizar esse caso.
 */
class RecalcularPerfilOnDisputeResolvida
{
    public function handle(PaymentDisputeResolvida $event): void
    {
        if ($event->dispute->tipo !== TipoPaymentDispute::ContestacaoConclusao) {
            return;
        }

        if ($event->dispute->resultado !== ResultadoPaymentDispute::Cancelado) {
            return;
        }

        RecalcularPerfilConfiancaJob::dispatch($event->servico->proposta->profissional_id);
    }
}
