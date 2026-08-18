<?php

namespace App\PropertyHistory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateTransferRequest extends FormRequest
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
            'para_cliente_id' => ['required_without:para_email', 'nullable', 'uuid', 'exists:usuarios,id'],
            'para_email' => ['required_without:para_cliente_id', 'nullable', 'email', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'para_cliente_id' => $this->filled('para_cliente_id') ? $this->input('para_cliente_id') : null,
            'para_email' => $this->filled('para_email') ? $this->input('para_email') : null,
        ]);
    }
}
