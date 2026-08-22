<?php

namespace App\Admin\Http\Controllers;

use App\Auth\Enums\TipoUsuario;
use App\Http\Controllers\Controller;
use App\Professionals\DocumentoProfissional;
use App\Professionals\Enums\StatusDocumentoProfissional;
use App\Professionals\Http\Requests\ReviewDocumentoProfissionalRequest;
use App\Professionals\Http\Resources\DocumentoProfissionalResource;
use App\Professionals\Services\ProfissionalVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentoProfissionalController extends Controller
{
    public function __construct(
        private readonly ProfissionalVerificationService $verificationService,
    ) {}

    public function download(Request $request, DocumentoProfissional $documento): StreamedResponse|JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Admin) {
            return ApiResponse::error('Apenas administradores podem visualizar documentos.', 403);
        }

        $disk = (string) config('filesystems.object_disk', 's3');

        if (! Storage::disk($disk)->exists($documento->arquivo)) {
            return ApiResponse::error('Arquivo não encontrado.', 404);
        }

        return Storage::disk($disk)->response($documento->arquivo);
    }

    public function review(
        ReviewDocumentoProfissionalRequest $request,
        DocumentoProfissional $documento,
    ): JsonResponse {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        if ($usuario->tipo !== TipoUsuario::Admin) {
            return ApiResponse::error('Apenas administradores podem revisar documentos.', 403);
        }

        if ($documento->status !== StatusDocumentoProfissional::Pendente) {
            return ApiResponse::error('Só documentos pendentes podem ser revisados.', 409);
        }

        $status = StatusDocumentoProfissional::from($request->string('status')->toString());

        if ($status === StatusDocumentoProfissional::Aprovado) {
            $documento = $this->verificationService->approve($documento, $usuario);
        } else {
            $documento = $this->verificationService->reject(
                $documento,
                $usuario,
                $request->string('motivo_rejeicao')->toString(),
            );
        }

        return ApiResponse::success(new DocumentoProfissionalResource($documento));
    }
}
