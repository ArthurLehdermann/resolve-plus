<?php

namespace App\PropertyHistory\Http\Resources;

use App\PropertyHistory\PropertyOwnershipTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PropertyOwnershipTransfer */
class PropertyOwnershipTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'de_cliente_id' => $this->de_cliente_id,
            'para_cliente_id' => $this->para_cliente_id,
            'para_email' => $this->para_email,
            'status' => $this->status->value,
            'criado_em' => $this->criado_em->utc()->toIso8601String(),
            'expira_em' => $this->expira_em?->utc()->toIso8601String(),
        ];
    }
}
