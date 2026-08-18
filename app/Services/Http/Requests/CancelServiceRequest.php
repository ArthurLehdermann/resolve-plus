<?php

namespace App\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
