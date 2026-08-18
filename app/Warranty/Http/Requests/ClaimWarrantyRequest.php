<?php

namespace App\Warranty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimWarrantyRequest extends FormRequest
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
            'descricao' => ['required', 'string', 'min:10'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'string', 'min:1'],
        ];
    }

    public function descricao(): string
    {
        return trim((string) $this->validated('descricao'));
    }

    /**
     * @return list<string>
     */
    public function photos(): array
    {
        /** @var list<string> */
        return array_values($this->validated('photos'));
    }
}
