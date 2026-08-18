<?php

namespace App\Ratings\Http\Resources;

use App\Ratings\Avaliacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Avaliacao */
class AvaliacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'servico_id' => $this->servico_id,
            'autor_id' => $this->autor_id,
            'alvo_id' => $this->alvo_id,
            'direcao' => $this->direcao->value,
            'nota' => $this->nota,
            'comentario' => $this->comentario,
            'criado_em' => $this->criado_em?->toIso8601String(),
        ];
    }
}
