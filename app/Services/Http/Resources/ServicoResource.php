<?php

namespace App\Services\Http\Resources;

use App\Services\Servico;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Servico */
class ServicoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proposal_id' => $this->proposta_id,
            'status' => $this->status->value,
            'started_at' => $this->inicio?->toIso8601String(),
            'finished_at' => $this->fim?->toIso8601String(),
            'notes' => $this->notas,
            'photos' => $this->fotos ?? [],
        ];
    }
}
