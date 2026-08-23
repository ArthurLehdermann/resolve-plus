<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Http\Requests\ForgotPasswordRequest;
use App\Auth\Http\Requests\LoginRequest;
use App\Auth\Http\Requests\MagicLinkRequest;
use App\Auth\Http\Requests\RegisterRequest;
use App\Auth\Http\Requests\ResetPasswordRequest;
use App\Auth\Http\Requests\VerifyMagicLinkRequest;
use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Mail\MagicLinkMail;
use App\Auth\Models\LinkMagico;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAGIC_LINK_TTL_MINUTES = 15;

    public function register(RegisterRequest $request): JsonResponse
    {
        $tipo = TipoUsuario::from($request->string('tipo')->toString());
        $status = $tipo === TipoUsuario::Cliente
            ? StatusConta::Ativa
            : StatusConta::PendenteVerificacao;

        $usuario = Usuario::query()->create([
            'tipo' => $tipo,
            'nome' => $request->string('nome')->toString(),
            'email' => $request->string('email')->toString(),
            'telefone' => $request->string('telefone')->toString(),
            'senha_hash' => null,
            'status' => $status,
        ]);

        $token = $usuario->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => new UsuarioResource($usuario),
            'token' => $token,
        ], 201);
    }

    /**
     * Senha só existe para o painel administrativo. Cliente e profissional
     * entram por código de e-mail (magic link) ou Google — conta desses tipos
     * nem chega a ter hash gravado.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->comEmail($request->string('email')->toString())->first();

        if ($usuario !== null && $usuario->tipo !== TipoUsuario::Admin) {
            throw ValidationException::withMessages([
                'email' => ['Esta conta entra por código de e-mail ou pelo Google, sem senha.'],
            ]);
        }

        if (
            $usuario === null
            || $usuario->senha_hash === null
            || ! Hash::check($request->string('senha')->toString(), $usuario->senha_hash)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $usuario->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => new UsuarioResource($usuario),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $usuario->currentAccessToken()->delete();

        return ApiResponse::success();
    }

    public function requestMagicLink(MagicLinkRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->comEmail($request->string('email')->toString())->first();

        if ($usuario !== null) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            LinkMagico::query()
                ->where('usuario_id', $usuario->id)
                ->whereNull('used_at')
                ->delete();

            LinkMagico::query()->create([
                'usuario_id' => $usuario->id,
                'token_hash' => hash('sha256', $codigo),
                'expires_at' => now()->addMinutes(self::MAGIC_LINK_TTL_MINUTES),
                'created_at' => now(),
            ]);

            Mail::to($usuario->email)->send(new MagicLinkMail(
                nome: $usuario->nome,
                codigo: $codigo,
                expiraEmMinutos: self::MAGIC_LINK_TTL_MINUTES,
            ));
        }

        return ApiResponse::success([
            'message' => 'Se o e-mail estiver cadastrado, enviaremos um código de acesso.',
        ]);
    }

    public function verifyMagicLink(VerifyMagicLinkRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->comEmail($request->string('email')->toString())->first();
        $codigoHash = hash('sha256', $request->string('codigo')->toString());

        $link = $usuario !== null
            ? LinkMagico::query()
                ->where('usuario_id', $usuario->id)
                ->where('token_hash', $codigoHash)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first()
            : null;

        if ($usuario === null || $link === null) {
            throw ValidationException::withMessages([
                'codigo' => ['Código inválido ou expirado.'],
            ]);
        }

        $link->forceFill(['used_at' => now()])->save();

        $token = $usuario->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => new UsuarioResource($usuario),
            'token' => $token,
        ]);
    }

    /**
     * Redefinição de senha existe só para o painel: mandar o e-mail para
     * cliente/profissional daria a eles um caminho de senha que o app não tem
     * mais. A resposta segue genérica para não revelar quem é admin.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->string('email')->toString();
        $usuario = Usuario::query()->comEmail($email)->first();

        if ($usuario?->tipo === TipoUsuario::Admin) {
            Password::sendResetLink(['email' => $email]);
        }

        return ApiResponse::success([
            'message' => 'Se o e-mail estiver cadastrado, enviaremos instruções de redefinição.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->comEmail($request->string('email')->toString())->first();

        if ($usuario !== null && $usuario->tipo !== TipoUsuario::Admin) {
            throw ValidationException::withMessages([
                'email' => ['Esta conta entra por código de e-mail ou pelo Google, sem senha.'],
            ]);
        }

        $status = Password::reset(
            [
                'email' => $request->string('email')->toString(),
                'password' => $request->string('senha')->toString(),
                'password_confirmation' => $request->string('senha_confirmation')->toString(),
                'token' => $request->string('token')->toString(),
            ],
            function (Usuario $usuario, string $password): void {
                $usuario->forceFill([
                    'senha_hash' => $password,
                ])->save();

                $usuario->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return ApiResponse::success([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }
}
