<?php

namespace App\Auth\Http\Resources;

use App\Auth\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo->value,
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'foto' => $this->foto,
            'status' => $this->status->value,
            'criado_em' => $this->created_at?->toIso8601String(),
        ];
    }
}
