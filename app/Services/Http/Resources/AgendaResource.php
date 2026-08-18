<?php

namespace App\Services\Http\Resources;

use App\Services\Agenda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agenda */
class AgendaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hora = (string) $this->hora;

        return [
            'id' => $this->id,
            'service_id' => $this->servico_id,
            'date' => $this->data?->format('Y-m-d'),
            'time' => substr($hora, 0, 5),
            'notes' => $this->observacoes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
