<?php

namespace App\Requests\Http\Resources;

use App\Requests\Solicitacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Solicitacao */
class SolicitacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'property_id' => $this->property_id,
            'category_id' => $this->categoria_id,
            'description' => $this->descricao,
            'scope' => $this->escopo,
            'desired_date' => $this->data_desejada?->toDateString(),
            'estimated_price_min' => $this->faixa_preco_min,
            'estimated_price_max' => $this->faixa_preco_max,
            'estimated_price_factor_bp' => $this->faixa_preco_fator_bp,
            'price_table_id' => $this->tabela_preco_id,
            'photos' => FotoSolicitacaoResource::collection($this->whenLoaded('fotos')),
            'criado_em' => $this->criado_em?->toIso8601String(),
        ];
    }
}
