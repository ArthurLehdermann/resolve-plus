<?php

namespace App\Requests\Http\Requests;

use App\Requests\TabelaPreco;
use Illuminate\Foundation\Http\FormRequest;

class StoreTabelaPrecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TabelaPreco::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'uuid', 'exists:categorias,id'],
            'cidade' => ['required', 'string', 'max:150'],
            'valor_min' => ['required', 'integer', 'min:1'],
            'valor_max' => ['required', 'integer', 'gte:valor_min'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
