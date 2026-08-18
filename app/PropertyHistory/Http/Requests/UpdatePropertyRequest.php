<?php

namespace App\PropertyHistory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
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
            'cep' => ['sometimes', 'required', 'string', 'max:16'],
            'logradouro' => ['sometimes', 'required', 'string', 'max:255'],
            'numero' => ['sometimes', 'required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['sometimes', 'required', 'string', 'max:100'],
            'cidade' => ['sometimes', 'required', 'string', 'max:100'],
            'estado' => ['sometimes', 'required', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'apelido' => ['nullable', 'string', 'max:100'],
            'chave_endereco' => ['prohibited'],
        ];
    }
}
