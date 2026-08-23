<?php

namespace App\Notifications\Listeners;

use App\Notifications\NotifyUser;
use App\Notifications\TipoNotificacao;
use App\Services\Events\ServiceApproved;
use App\Services\Events\ServiceFinished;
use App\Services\Events\ServiceStarted;

/**
 * Os três marcos da execução em um listener só: mudam o destinatário e o texto,
 * não a lógica. `clienteId()`/`profissionalId()` do Servico já resolvem também
 * a revisita de garantia, que não tem proposta própria.
 */
class NotifyOnServiceProgress
{
    public function __construct(private readonly NotifyUser $notify) {}

    public function started(ServiceStarted $event): void
    {
        ($this->notify)(
            $event->servico->clienteId(),
            TipoNotificacao::ServicoIniciado,
            'O serviço começou',
            'O profissional iniciou o atendimento. Acompanhe pelo app.',
            ['servico_id' => (string) $event->servico->id],
        );
    }

    public function finished(ServiceFinished $event): void
    {
        ($this->notify)(
            $event->servico->clienteId(),
            TipoNotificacao::ServicoConcluido,
            'O serviço foi concluído',
            'O profissional registrou a conclusão. Aprove para liberar o pagamento ou conteste se algo não estiver certo.',
            ['servico_id' => (string) $event->servico->id],
        );
    }

    public function approved(ServiceApproved $event): void
    {
        $corpo = $event->automatico
            ? 'O prazo de aprovação terminou e o serviço foi aprovado automaticamente. O pagamento foi liberado.'
            : 'O cliente aprovou o serviço e o pagamento foi liberado.';

        ($this->notify)(
            $event->servico->profissionalId(),
            TipoNotificacao::ServicoAprovado,
            'Serviço aprovado',
            $corpo,
            ['servico_id' => (string) $event->servico->id],
        );
    }
}
