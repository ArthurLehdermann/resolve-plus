<?php

namespace App\Services\Http\Requests;

use App\Payments\TipoPaymentDispute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenDisputeRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(array_column(TipoPaymentDispute::cases(), 'value'))],
            'motivo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function tipo(): TipoPaymentDispute
    {
        return TipoPaymentDispute::from($this->string('tipo')->toString());
    }

    public function motivo(): ?string
    {
        $motivo = $this->input('motivo');

        return is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null;
    }
}
