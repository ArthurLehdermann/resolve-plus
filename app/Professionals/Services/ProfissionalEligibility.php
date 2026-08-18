<?php

namespace App\Professionals\Services;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;

/**
 * INV-002: profissional só recebe solicitações com Conta.status = ATIVA.
 */
final class ProfissionalEligibility
{
    public static function podeReceberSolicitacoes(Usuario $usuario): bool
    {
        return $usuario->tipo === TipoUsuario::Profissional
            && $usuario->status === StatusConta::Ativa;
    }
}
