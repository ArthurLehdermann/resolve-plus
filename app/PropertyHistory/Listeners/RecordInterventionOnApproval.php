<?php

namespace App\PropertyHistory\Listeners;

use App\PropertyHistory\OrigemIntervention;
use App\PropertyHistory\RecordIntervention;
use App\Services\Events\ServiceApproved;
use RuntimeException;

class RecordInterventionOnApproval
{
    public function __construct(private readonly RecordIntervention $recordIntervention) {}

    public function handle(ServiceApproved $event): void
    {
        $servico = $event->servico->loadMissing('proposta.solicitacao');
        $propertyId = $servico->propertyId();

        if ($propertyId === '') {
            throw new RuntimeException(
                "Serviço {$servico->id} aprovado sem property_id; P7/INV-060 exige Intervention no prontuário.",
            );
        }

        ($this->recordIntervention)(
            propertyId: $propertyId,
            origem: OrigemIntervention::Plataforma,
            categoria: 'servico',
            resumo: 'Serviço aprovado na plataforma.',
            data: $servico->fim ?? now(),
            servicoId: $servico->id,
        );
    }
}
