<?php

namespace App\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Users\Http\Requests\UpdateProfileRequest;
use App\Users\Http\Requests\UploadPhotoRequest;
use App\Users\Http\Resources\UsuarioMeResource;
use App\Users\Jobs\ProcessUserAvatarJob;
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
