<?php

namespace App\Services\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\ApproveService;
use App\Services\Actions\ContestService;
use App\Services\Actions\FinishService;
use App\Services\Actions\StartService;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Requests\ContestServiceRequest;
use App\Services\Http\Requests\FinishServiceRequest;
use App\Services\Http\Resources\ServicoResource;
use App\Services\Servico;
use App\Support\ApiResponse;
use App\Support\IdempotentOperation;
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

    public function finish(FinishServiceRequest $request, string $id, FinishService $action): JsonResponse
    {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $servico = $action($servico, $this->usuario($request), $request->notes(), $request->photos());

        return ApiResponse::success(new ServicoResource($servico));
    }

    public function approve(
        Request $request,
        string $id,
        ApproveService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.approve:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $servico = $action->byCliente($servico, $this->usuario($request));

            return ApiResponse::success(new ServicoResource($servico));
        });
    }

    public function contest(
        ContestServiceRequest $request,
        string $id,
        ContestService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.contest:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $servico = $action($servico, $this->usuario($request), $request->motivo());

            return ApiResponse::success(new ServicoResource($servico));
        });
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
