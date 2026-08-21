<?php

namespace App\Warranty\Listeners;

use App\Users\Jobs\RecalcularPerfilConfiancaJob;
use App\Warranty\Events\GarantiaAcionada;

/**
 * `foundation/05-trust-level.md`, "reclamação" item 3: GarantiaAcionada
 * conta como reclamação do profissional daquele serviço mesmo com a
 * garantia ainda em mediação. Sem este listener, `reclamacoes_12m` só
 * mudava quando o profissional recebesse uma nova avaliação (o único
 * gatilho de recálculo existente até 2026-08-21), deixando o nível de
 * confiança exibido ao cliente desatualizado após um acionamento de
 * garantia.
 */
class RecalcularPerfilOnGarantiaAcionada
{
    public function handle(GarantiaAcionada $event): void
    {
        RecalcularPerfilConfiancaJob::dispatch($event->garantia->servico->proposta->profissional_id);
    }
}
