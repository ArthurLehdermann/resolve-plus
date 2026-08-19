<?php

namespace App\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDisputeRequest extends FormRequest
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
            'resultado' => ['required', 'string', Rule::in(['APROVADO', 'CANCELADO'])],
            'justificativa' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
