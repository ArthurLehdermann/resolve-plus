<?php

namespace App\Professionals\Http\Requests;

use App\Professionals\Enums\StatusDocumentoProfissional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDocumentoProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    StatusDocumentoProfissional::Aprovado->value,
                    StatusDocumentoProfissional::Rejeitado->value,
                ]),
            ],
            'motivo_rejeicao' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:status,'.StatusDocumentoProfissional::Rejeitado->value,
            ],
        ];
    }
}
