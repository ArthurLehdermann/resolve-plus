<?php

namespace App\Users\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Users\Http\Requests\UpdateProfileRequest;
use App\Users\Http\Requests\UploadPhotoRequest;
use App\Users\Http\Resources\UsuarioMeResource;
use App\Users\Jobs\ProcessUserAvatarJob;
use App\Users\NivelConfianca;
use App\Users\PerfilProfissional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        return ApiResponse::success(new UsuarioMeResource($usuario));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $usuario->fill($request->safe()->only(['nome', 'email', 'telefone']));
        $usuario->save();

        if ($usuario->tipo === TipoUsuario::Profissional && $request->has('categorias_atendidas')) {
            $perfil = PerfilProfissional::query()->firstOrNew(
                ['usuario_id' => $usuario->id],
            );
            $perfil->categorias_atendidas = $request->input('categorias_atendidas');

            if (! $perfil->exists) {
                $perfil->nivel_confianca = NivelConfianca::Verificado;
                $perfil->servicos_aprovados = 0;
                $perfil->nota_media_dez = null;
                $perfil->taxa_cancelamento_pct = 0;
                $perfil->reclamacoes_12m = 0;
                $perfil->nivel_atualizado_em = now();
            }

            $perfil->save();
        }

        return ApiResponse::success(new UsuarioMeResource($usuario->refresh()));
    }

    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $file = $request->file('photo');
        $disk = (string) config('filesystems.object_disk', 's3');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = Str::uuid()->toString().'.'.$extension;
        $directory = 'avatars/'.$usuario->id;

        $path = $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        if ($path === false) {
            return ApiResponse::error('Falha ao enviar o avatar para o Object Storage.', 500);
        }

        $usuario->forceFill([
            'foto' => $path,
        ])->save();

        ProcessUserAvatarJob::dispatch($usuario->id, $path);

        return ApiResponse::success(new UsuarioMeResource($usuario->refresh()), 202);
    }
}
