<?php

namespace App\Professionals\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Http\Controllers\Controller;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use App\Professionals\Http\Requests\UploadDocumentoProfissionalRequest;
use App\Professionals\Http\Resources\DocumentoProfissionalResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ProfissionalDocumentoController extends Controller
{
    public function store(UploadDocumentoProfissionalRequest $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Profissional) {
            return ApiResponse::error('Apenas profissionais podem enviar documentos.', 403);
        }

        $tipo = TipoDocumentoProfissional::from($request->string('tipo')->toString());
        $file = $request->file('arquivo');
        $disk = (string) config('filesystems.object_disk', 's3');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('documentos-profissional/'.$usuario->id, $filename, [
            'disk' => $disk,
            'visibility' => 'private',
        ]);

        if ($path === false) {
            return ApiResponse::error('Falha ao enviar documento.', 500);
        }

        $documento = DocumentoProfissional::query()->create([
            'profissional_id' => $usuario->id,
            'tipo' => $tipo,
            'arquivo' => $path,
            'status' => StatusDocumentoProfissional::Pendente,
            'apolice_numero' => $request->string('apolice_numero')->toString() ?: null,
            'vigencia_inicio' => $request->date('vigencia_inicio'),
            'vigencia_fim' => $request->date('vigencia_fim'),
        ]);

        return ApiResponse::success(new DocumentoProfissionalResource($documento), 201);
    }
}
