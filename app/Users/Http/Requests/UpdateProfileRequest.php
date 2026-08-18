<?php

namespace App\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $usuarioId = $this->user()?->id;

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('usuarios', 'email')->ignore($usuarioId),
            ],
            'telefone' => ['sometimes', 'required', 'string', 'max:20'],
        ];
    }
}
