<?php

namespace App\Categories\Http\Requests;

use App\Categories\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $categoria = $this->route('categoria');

        return $categoria instanceof Categoria
            && ($this->user()?->can('update', $categoria) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoria = $this->route('categoria');
        $id = $categoria instanceof Categoria ? $categoria->id : $categoria;

        return [
            'codigo' => ['sometimes', 'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('categorias', 'codigo')->ignore($id)],
            'nome' => ['sometimes', 'required', 'string', 'max:150'],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
            'template_escopo' => ['sometimes', 'required', 'array', 'min:1'],
            'template_escopo.*' => ['required', 'array'],
            'template_escopo.*.tipo' => ['required', 'string', Rule::in(['int', 'number', 'enum', 'bool', 'string'])],
            'template_escopo.*.obrigatorio' => ['required', 'boolean'],
            'template_escopo.*.rotulo' => ['required', 'string'],
        ];
    }
}
