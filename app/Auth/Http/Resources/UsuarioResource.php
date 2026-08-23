<?php

namespace App\Auth\Http\Resources;

use App\Auth\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            // Avatar vindo do Google já é URL pronta; o que o usuário sobe é
            // caminho no disco e precisa passar pelo Storage.
            'foto' => $this->foto === null
                ? null
                : (str_starts_with($this->foto, 'http')
                    ? $this->foto
                    : Storage::disk((string) config('filesystems.object_disk', 's3'))->url($this->foto)),
            'status' => $this->status->value,
            'criado_em' => $this->created_at?->toIso8601String(),
        ];
    }
}
