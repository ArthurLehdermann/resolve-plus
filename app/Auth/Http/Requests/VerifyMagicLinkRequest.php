<?php

namespace App\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMagicLinkRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ];
    }
}
