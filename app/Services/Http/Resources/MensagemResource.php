<?php

namespace App\Services\Http\Resources;

use App\Services\Mensagem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mensagem */
class MensagemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->servico_id,
            'sender_id' => $this->remetente_id,
            'text' => $this->texto,
            'warning' => $this->when(
                $this->contactLeakWarning !== null,
                $this->contactLeakWarning,
            ),
            'attachment' => $this->anexo,
            'sent_at' => $this->enviado_em?->toIso8601String(),
        ];
    }
}
