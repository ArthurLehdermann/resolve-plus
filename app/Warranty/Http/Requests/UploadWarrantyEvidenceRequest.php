<?php

namespace App\Warranty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadWarrantyEvidenceRequest extends FormRequest
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
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }
}
