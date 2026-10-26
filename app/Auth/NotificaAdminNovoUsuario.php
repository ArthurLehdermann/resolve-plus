<?php

namespace App\Auth;

use App\Auth\Mail\NovoUsuarioAdminMail;
use App\Auth\Models\Usuario;
use Illuminate\Support\Facades\Mail;

/** Avisa ADMIN_EMAIL quando uma conta nasce, venha do formulário ou do Google. */
class NotificaAdminNovoUsuario
{
    public static function enviar(Usuario $usuario, string $origem): void
    {
        $destino = (string) config('mail.admin_address');

        if ($destino === '') {
            return;
        }

        Mail::to($destino)->queue(new NovoUsuarioAdminMail($usuario, $origem));
    }
}
