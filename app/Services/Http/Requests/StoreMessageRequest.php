<?php

namespace App\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
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
            'text' => ['required', 'string', 'max:5000'],
            'attachment' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
