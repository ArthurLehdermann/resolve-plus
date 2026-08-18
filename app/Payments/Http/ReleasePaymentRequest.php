<?php

namespace App\Payments\Http;

use Illuminate\Foundation\Http\FormRequest;

class ReleasePaymentRequest extends FormRequest
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
            'justificativa' => ['required', 'string', 'min:10', 'max:2000'],
            'responsavel_id' => ['sometimes', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'justificativa.required' => 'Justificativa é obrigatória para liberação manual (INV-041).',
            'justificativa.min' => 'Justificativa é obrigatória para liberação manual (INV-041).',
        ];
    }
}
