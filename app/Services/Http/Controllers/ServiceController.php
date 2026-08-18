<?php

namespace App\Services\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\StartService;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function start(Request $request, string $id, StartService $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $servico = $action($servico, $this->usuario($request));

        return ApiResponse::success(new ServicoResource($servico));
    }

    private function usuario(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw ServiceException::forbidden('Não autenticado.');
        }

        return $usuario;
    }
}
