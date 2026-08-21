<?php

namespace App\Requests\Http\Resources;

use App\Requests\TabelaPreco;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TabelaPreco */
class TabelaPrecoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoria_id' => $this->categoria_id,
            'cidade' => $this->cidade,
            'valor_min' => $this->valor_min,
            'valor_max' => $this->valor_max,
            'ativo' => $this->ativo,
            'criado_em' => $this->criado_em?->toIso8601String(),
        ];
    }
}
