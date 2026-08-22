<?php

namespace App\Auth\Http\Requests;

/**
 * Normaliza o e-mail antes da validação rodar.
 *
 * Sem isto, `unique:usuarios,email` compara a grafia crua e deixa passar
 * "Fulano@x.com" quando "fulano@x.com" já existe - e o login com a caixa
 * trocada não encontra a conta (`Usuario::scopeComEmail`).
 */
trait NormalizaEmail
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('email')) {
            return;
        }

        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $this->merge(['email' => mb_strtolower(trim($email))]);
    }
}
