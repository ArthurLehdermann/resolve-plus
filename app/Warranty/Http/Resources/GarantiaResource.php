<?php

namespace App\Warranty\Http\Resources;

use App\Warranty\Garantia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Garantia */
class GarantiaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'servico_id' => $this->servico_id,
            'inicio' => $this->inicio?->toIso8601String(),
            'fim' => $this->fim?->toIso8601String(),
            'status' => $this->status->value,
            'responsavel_financeiro' => $this->responsavel_financeiro->value,
            'claims' => $this->whenLoaded('claims', fn () => $this->claims->map(fn ($claim) => [
                'id' => $claim->id,
                'descricao' => $claim->descricao,
                'photos' => $claim->photos,
                'criado_em' => $claim->criado_em?->toIso8601String(),
            ])),
        ];
    }
}
