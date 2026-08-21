<?php

namespace App\Professionals\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Http\Controllers\Controller;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Enums\TipoDocumentoProfissional;
use App\Professionals\Http\Requests\UploadDocumentoProfissionalRequest;
use App\Professionals\Http\Resources\DocumentoProfissionalResource;
use App\Professionals\Services\RequiredDocumentTypes;
use App\Support\ApiResponse;
use App\Users\PerfilProfissional;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfissionalDocumentoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Profissional) {
            return ApiResponse::error('Apenas profissionais podem consultar documentos.', 403);
        }

        $perfil = PerfilProfissional::query()->where('usuario_id', $usuario->id)->first();
        $categorias = $perfil?->categorias_atendidas ?? [];

        if ($categorias === []) {
            return ApiResponse::success([
                'categorias_atendidas' => [],
                'slots' => [],
            ]);
        }

        $documentosPorTipo = DocumentoProfissional::query()
            ->where('profissional_id', $usuario->id)
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (DocumentoProfissional $documento) => $documento->tipo->value)
            ->keyBy(fn (DocumentoProfissional $documento) => $documento->tipo->value);

        $slots = collect(RequiredDocumentTypes::forCategorias($categorias))
            ->map(function (TipoDocumentoProfissional $tipo) use ($documentosPorTipo) {
                $documento = $documentosPorTipo->get($tipo->value);

                return [
                    'tipo' => $tipo->value,
                    'documento' => $documento === null ? null : (new DocumentoProfissionalResource($documento))->resolve(),
                ];
            })
            ->values();

        return ApiResponse::success([
            'categorias_atendidas' => $categorias,
            'slots' => $slots,
        ]);
    }

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
