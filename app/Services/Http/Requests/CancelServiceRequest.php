<?php

namespace App\Services\Http\Requests;

use App\Auth\Models\Usuario;
use App\Services\Servico;
use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        if (! $usuario instanceof Usuario) {
            return false;
        }

        $id = $this->route('id');

        if (! is_string($id)) {
            return false;
        }

        $servico = Servico::query()->find($id);

        if ($servico === null) {
            return true;
        }

        return $servico->isParticipante($usuario);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function motivo(): ?string
    {
        $motivo = $this->input('motivo');

        return is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null;
    }
}
