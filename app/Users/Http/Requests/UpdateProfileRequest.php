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
            'categorias_atendidas' => ['sometimes', 'array', 'min:1'],
            /*
             * Aceita o que a tabela de categorias oferece hoje, não a lista
             * fixa do seed: a tela monta as opções a partir de GET /categorias
             * e, quando a validação olhava só para o seed do MVP, qualquer
             * categoria criada depois virava "inválida" no salvar — o
             * profissional marcava uma opção que o próprio app tinha exibido
             * e levava erro.
             */
            'categorias_atendidas.*' => [
                'string',
                Rule::exists('categorias', 'codigo')->where('ativo', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categorias_atendidas.min' => 'Escolha ao menos uma categoria.',
            'categorias_atendidas.*.exists' => 'Categoria indisponível: recarregue a lista e tente de novo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'email' => 'e-mail',
            'telefone' => 'telefone',
            'categorias_atendidas' => 'categorias atendidas',
        ];
    }
}
