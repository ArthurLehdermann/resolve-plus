<?php

namespace App\Proposals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
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
            'price' => ['required', 'integer', 'min:1'],
            'deadline_days' => ['required', 'integer', 'min:1'],
            'warranty_days' => ['required', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
