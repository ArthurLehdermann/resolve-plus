<?php

namespace App\Warranty\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Warranty\Actions\ClaimWarranty;
use App\Warranty\Exceptions\WarrantyException;
use App\Warranty\Garantia;
use App\Warranty\Http\Requests\ClaimWarrantyRequest;
use App\Warranty\Http\Requests\UploadWarrantyEvidenceRequest;
use App\Warranty\Http\Resources\GarantiaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WarrantyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $this->usuario($request);

        $garantias = Garantia::query()
            ->with(['servico.proposta.solicitacao', 'servico.garantiaOrigem.servico.proposta.solicitacao'])
            ->whereHas('servico', function ($query) use ($usuario): void {
                $query->where(function ($inner) use ($usuario): void {
                    $inner->whereHas('proposta.solicitacao', fn ($solicitacao) => $solicitacao
                        ->where('cliente_id', $usuario->id))
                        ->orWhereHas('proposta', fn ($proposta) => $proposta
                            ->where('profissional_id', $usuario->id))
                        ->orWhereHas('garantiaOrigem.servico.proposta.solicitacao', fn ($solicitacao) => $solicitacao
                            ->where('cliente_id', $usuario->id))
                        ->orWhereHas('garantiaOrigem.servico.proposta', fn ($proposta) => $proposta
                            ->where('profissional_id', $usuario->id));
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(GarantiaResource::collection($garantias));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $garantia = Garantia::query()
            ->with(['servico.proposta.solicitacao', 'servico.garantiaOrigem.servico.proposta.solicitacao', 'claims'])
            ->findOrFail($id);

        $this->autorizarVisualizacao($garantia, $this->usuario($request));

        return ApiResponse::success(new GarantiaResource($garantia));
    }

    /**
     * Sobe uma evidência e devolve o caminho para entrar em `photos` do claim.
     *
     * `POST /warranties/{id}/claim` exige pelo menos uma foto (string), e não
     * existia como o app produzir essa string: mesmo padrão de
     * `POST /requests/{id}/photos`, agora para a garantia.
     */
    public function uploadEvidence(UploadWarrantyEvidenceRequest $request, string $id): JsonResponse
    {
        $garantia = Garantia::query()->with('servico.proposta.solicitacao')->findOrFail($id);
        $this->autorizarVisualizacao($garantia, $this->usuario($request));

        $file = $request->file('photo');
        $disk = (string) config('filesystems.object_disk', 's3');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $path = $file->storeAs('warranties/'.$garantia->id, Str::uuid()->toString().'.'.$extension, [
            'disk' => $disk,
            'visibility' => 'public',
        ]);

        if ($path === false) {
            return ApiResponse::error('Falha ao enviar a evidência para o Object Storage.', 500);
        }

        return ApiResponse::success(['path' => $path, 'url' => Storage::disk($disk)->url($path)], 201);
    }

    public function claim(ClaimWarrantyRequest $request, string $id, ClaimWarranty $action): JsonResponse
    {
        $garantia = Garantia::query()->findOrFail($id);
        $garantia = $action($garantia, $this->usuario($request), $request->descricao(), $request->photos());

        return ApiResponse::success(new GarantiaResource($garantia));
    }

    private function autorizarVisualizacao(Garantia $garantia, Usuario $usuario): void
    {
        $servico = $garantia->servico;

        if ($servico->isClienteDono($usuario) || $servico->isProfissionalResponsavel($usuario)) {
            return;
        }

        throw WarrantyException::forbidden('Sem permissão para visualizar esta garantia.');
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw WarrantyException::forbidden('Não autenticado.');
        }

        return $usuario;
    }
}
