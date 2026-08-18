<?php

namespace App\Categories\Policies;

use App\Auth\Enums\TipoUsuario;
use App\Auth\Models\Usuario;
use App\Categories\Models\Categoria;

class CategoriaPolicy
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

    public function view(Usuario $usuario, Categoria $categoria): bool
    {
        return false;
    }

    public function create(Usuario $usuario): bool
    {
        return false;
    }

    public function update(Usuario $usuario, Categoria $categoria): bool
    {
        return false;
    }

    public function delete(Usuario $usuario, Categoria $categoria): bool
    {
        return false;
    }
}
