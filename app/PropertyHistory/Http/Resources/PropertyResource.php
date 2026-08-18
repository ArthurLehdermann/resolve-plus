<?php

namespace App\PropertyHistory\Http\Resources;

use App\PropertyHistory\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Property */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cep' => $this->cep,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'apelido' => $this->apelido,
            'chave_endereco' => $this->chave_endereco,
            'criado_em' => $this->created_at?->utc()->toIso8601String(),
        ];
    }
}
