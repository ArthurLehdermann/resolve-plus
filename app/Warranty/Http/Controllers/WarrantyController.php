<?php

namespace App\Warranty\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Warranty\Actions\ClaimWarranty;
use App\Warranty\Exceptions\WarrantyException;
use App\Warranty\Garantia;
use App\Warranty\Http\Requests\ClaimWarrantyRequest;
use App\Warranty\Http\Resources\GarantiaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
