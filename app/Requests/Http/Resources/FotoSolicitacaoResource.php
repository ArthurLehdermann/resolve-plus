<?php

namespace App\Requests\Http\Resources;

use App\Requests\FotoSolicitacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FotoSolicitacao */
class FotoSolicitacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'ordem' => $this->ordem,
        ];
    }
}
