<?php

namespace App\Professionals\Http\Requests;

use App\Professionals\Enums\TipoDocumentoProfissional;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentoProfissionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', Rule::in(array_column(TipoDocumentoProfissional::cases(), 'value'))],
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'apolice_numero' => ['nullable', 'string', 'max:100'],
            'vigencia_inicio' => ['nullable', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
        ];
    }
}
