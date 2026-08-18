<?php

namespace App\Services\Http\Controllers;

use App\Auth\Models\Usuario;
use App\Http\Controllers\Controller;
use App\Services\Actions\ApproveService;
use App\Services\Actions\CancelService;
use App\Services\Actions\ContestService;
use App\Services\Actions\FinishService;
use App\Services\Actions\OpenDispute;
use App\Services\Actions\StartService;
use App\Services\Exceptions\ServiceException;
use App\Services\Http\Requests\CancelServiceRequest;
use App\Services\Http\Requests\ContestServiceRequest;
use App\Services\Http\Requests\FinishServiceRequest;
use App\Services\Http\Requests\OpenDisputeRequest;
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

    public function cancel(
        CancelServiceRequest $request,
        string $id,
        CancelService $action,
        IdempotentOperation $idempotency,
    ): JsonResponse {
        return $idempotency->run($request, "services.cancel:{$id}", function () use ($request, $id, $action): JsonResponse {
            $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
            $result = $action($servico, $this->usuario($request), $request->motivo());

            $payload = [
                'servico' => new ServicoResource($result['servico']),
            ];

            if ($result['multa'] !== null) {
                $payload['multa'] = $result['multa'];
            }

            if ($result['dispute'] !== null) {
                $payload['dispute'] = [
                    'id' => $result['dispute']->id,
                    'tipo' => $result['dispute']->tipo->value,
                    'status' => $result['dispute']->status->value,
                ];
            }

            return ApiResponse::success($payload);
        });
    }

    public function openDispute(
        OpenDisputeRequest $request,
        string $id,
        OpenDispute $action,
    ): JsonResponse {
        $servico = Servico::query()->with('proposta.solicitacao')->findOrFail($id);
        $dispute = $action($servico, $this->usuario($request), $request->tipo(), $request->motivo());

        return ApiResponse::success([
            'id' => $dispute->id,
            'tipo' => $dispute->tipo->value,
            'status' => $dispute->status->value,
            'servico_id' => $dispute->servico_id,
        ], 201);
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
