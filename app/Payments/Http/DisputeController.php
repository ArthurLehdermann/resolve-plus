<?php

namespace App\Payments\Http;

use App\Http\Controllers\Controller;
use App\Payments\Actions\ResolveDispute;
use App\Payments\Http\Requests\ResolveDisputeRequest;
use App\Payments\PaymentDispute;
use App\Payments\PaymentDomainException;
use App\Payments\ResultadoPaymentDispute;
use App\Services\Exceptions\ServiceException;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DisputeController extends Controller
{
    public function resolve(
        ResolveDisputeRequest $request,
        string $id,
        ResolveDispute $action,
    ): JsonResponse {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        $dispute = PaymentDispute::query()->with('servico')->find($id);

        if ($dispute === null) {
            return ApiResponse::error('Disputa não encontrada.', 404);
        }

        try {
            $dispute = $action(
                $dispute,
                $usuario,
                ResultadoPaymentDispute::from($request->string('resultado')->toString()),
                $request->string('justificativa')->toString(),
                $request->ip(),
            );
        } catch (ServiceException $exception) {
            return $exception->render();
        } catch (PaymentDomainException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->status);
        }

        return ApiResponse::success([
            'id' => $dispute->id,
            'tipo' => $dispute->tipo->value,
            'status' => $dispute->status->value,
            'resultado' => $dispute->resultado?->value,
            'servico_id' => $dispute->servico_id,
            'servico_status' => $dispute->servico?->status->value,
        ]);
    }
}
