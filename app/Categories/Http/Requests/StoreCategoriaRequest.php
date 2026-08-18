<?php

namespace App\Categories\Http\Requests;

use App\Categories\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Categoria::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:categorias,codigo'],
            'nome' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
            'template_escopo' => ['required', 'array', 'min:1'],
            'template_escopo.*' => ['required', 'array'],
            'template_escopo.*.tipo' => ['required', 'string', Rule::in(['int', 'number', 'enum', 'bool', 'string'])],
            'template_escopo.*.obrigatorio' => ['required', 'boolean'],
            'template_escopo.*.rotulo' => ['required', 'string'],
        ];
    }
}
