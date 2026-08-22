<?php

namespace App\Professionals\Http\Resources;

use App\Auth\Http\Resources\UsuarioResource;
use App\Professionals\DocumentoProfissional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentoProfissional
 */
class DocumentoProfissionalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profissional_id' => $this->profissional_id,
            'profissional' => $this->whenLoaded(
                'profissional',
                fn () => new UsuarioResource($this->profissional),
            ),
            'tipo' => $this->tipo->value,
            'arquivo' => $this->arquivo,
            'status' => $this->status->value,
            'motivo_rejeicao' => $this->motivo_rejeicao,
            'revisado_por_id' => $this->revisado_por_id,
            'revisado_em' => $this->revisado_em?->utc()->toIso8601String(),
            'apolice_numero' => $this->apolice_numero,
            'vigencia_inicio' => $this->vigencia_inicio?->toDateString(),
            'vigencia_fim' => $this->vigencia_fim?->toDateString(),
            'criado_em' => $this->created_at?->utc()->toIso8601String(),
            'atualizado_em' => $this->updated_at?->utc()->toIso8601String(),
        ];
    }
}
