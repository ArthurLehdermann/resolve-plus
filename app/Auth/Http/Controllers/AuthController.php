<?php

namespace App\Auth\Http\Controllers;

use App\Auth\Enums\StatusConta;
use App\Auth\Enums\TipoUsuario;
use App\Auth\Http\Requests\ForgotPasswordRequest;
use App\Auth\Http\Requests\LoginRequest;
use App\Auth\Http\Requests\RegisterRequest;
use App\Auth\Http\Requests\ResetPasswordRequest;
use App\Auth\Http\Resources\UsuarioResource;
use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
            'senha_hash' => $request->string('senha')->toString(),
            'status' => $status,
        ]);

        $token = $usuario->createToken('auth')->plainTextToken;

        return ApiResponse::success([
            'user' => new UsuarioResource($usuario),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $usuario = Usuario::query()->where('email', $request->string('email')->toString())->first();

        if ($usuario === null || ! Hash::check($request->string('senha')->toString(), $usuario->senha_hash)) {
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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink([
            'email' => $request->string('email')->toString(),
        ]);

        return ApiResponse::success([
            'message' => 'Se o e-mail estiver cadastrado, enviaremos instruções de redefinição.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
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
