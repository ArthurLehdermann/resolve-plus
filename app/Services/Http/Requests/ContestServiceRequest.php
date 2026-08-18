<?php

namespace App\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContestServiceRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:2000'],
        ];
    }

    public function motivo(): string
    {
        return $this->string('motivo')->toString();
    }
}
