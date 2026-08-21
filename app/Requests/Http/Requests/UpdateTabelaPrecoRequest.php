<?php

namespace App\Requests\Http\Requests;

use App\Requests\TabelaPreco;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTabelaPrecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tabelaPreco')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'valor_min' => ['sometimes', 'integer', 'min:1'],
            'valor_max' => ['sometimes', 'integer'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var TabelaPreco $tabelaPreco */
            $tabelaPreco = $this->route('tabelaPreco');

            $min = $this->has('valor_min') ? (int) $this->input('valor_min') : $tabelaPreco->valor_min;
            $max = $this->has('valor_max') ? (int) $this->input('valor_max') : $tabelaPreco->valor_max;

            if ($max < $min) {
                $validator->errors()->add('valor_max', 'Deve ser maior ou igual a valor_min.');
            }
        });
    }
}
