<?php

namespace App\Auth\Http\Requests;

use App\Auth\Enums\TipoUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'tipo' => ['required', Rule::in([
                TipoUsuario::Cliente->value,
                TipoUsuario::Profissional->value,
            ])],
            'nome' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            'telefone' => ['required', 'string', 'max:20'],
            'senha' => ['required', 'string', 'min:8'],
        ];
    }
}
