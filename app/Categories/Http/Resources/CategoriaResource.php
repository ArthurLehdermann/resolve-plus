<?php

namespace App\Categories\Http\Resources;

use App\Categories\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Categoria */
class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'ativo' => $this->ativo,
            'template_escopo' => $this->template_escopo,
            'criado_em' => $this->created_at?->toIso8601String(),
            'atualizado_em' => $this->updated_at?->toIso8601String(),
        ];
    }
}
