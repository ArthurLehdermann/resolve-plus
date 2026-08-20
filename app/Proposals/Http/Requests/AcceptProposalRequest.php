<?php

namespace App\Proposals\Http\Requests;

use App\Payments\MetodoPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptProposalRequest extends FormRequest
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
            'metodo_pagamento' => ['required', Rule::in(array_column(MetodoPagamento::cases(), 'value'))],
            'credit_card_token' => ['required_if:metodo_pagamento,'.MetodoPagamento::Cartao->value, 'nullable', 'string'],
        ];
    }

    public function metodoPagamento(): MetodoPagamento
    {
        return MetodoPagamento::from($this->string('metodo_pagamento')->toString());
    }

    public function creditCardToken(): ?string
    {
        return $this->string('credit_card_token')->toString() ?: null;
    }
}
