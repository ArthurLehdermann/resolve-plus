<?php

namespace App\Requests\Policies;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Requests\TabelaPreco;

class TabelaPrecoPolicy
{
    public function before(Usuario $usuario, string $ability): ?bool
    {
        if ($usuario->tipo === TipoUsuario::Admin) {
            return true;
        }

        return null;
    }

    public function viewAny(Usuario $usuario): bool
    {
        return false;
    }

    public function create(Usuario $usuario): bool
    {
        return false;
    }

    public function update(Usuario $usuario, TabelaPreco $tabelaPreco): bool
    {
        return false;
    }
}
