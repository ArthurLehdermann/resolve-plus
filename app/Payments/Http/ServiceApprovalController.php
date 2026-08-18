<?php

namespace App\Payments\Http;

use App\Http\Controllers\Controller;
use App\Payments\CapturePayment;
use App\Payments\Idempotency;
use App\Payments\MetodoPagamento;
use App\Payments\PaymentAuthorization;
use App\Payments\PaymentDomainException;
use App\Payments\Servico;
use App\Payments\StatusPaymentAuthorization;
use App\Payments\StatusServico;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceApprovalController extends Controller
{
    public function approve(Request $request, string $id, Idempotency $idempotency, CapturePayment $capture): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario === null) {
            return ApiResponse::error('Não autenticado.', 401);
        }

        return $idempotency->remember($request, "services.approve.{$id}", function () use ($usuario, $id, $capture): JsonResponse {
            $servico = Servico::query()->find($id);

            if ($servico === null) {
                return ApiResponse::error('Serviço não encontrado.', 404);
            }

            if ($servico->cliente_id !== $usuario->id) {
                return ApiResponse::error('Apenas o cliente do serviço pode aprovar.', 403);
            }

            if ($servico->status === StatusServico::Aprovado) {
                return ApiResponse::error('Serviço já aprovado.', 409);
            }

            if ($servico->status !== StatusServico::AguardandoAprovacao) {
                return ApiResponse::error('Serviço não está aguardando aprovação.', 409);
            }

            $authorization = $this->authorizationForCapture($servico);

            if ($authorization === null) {
                return ApiResponse::error('Não há autorização de pagamento vigente para este serviço.', 409);
            }

            try {
                if ($authorization->status === StatusPaymentAuthorization::Autorizado
                    && $authorization->metodo === MetodoPagamento::Cartao) {
                    $authorization = $capture($authorization, [
                        'motivo' => 'SERVICO_APROVADO',
                    ]);
                }
            } catch (PaymentDomainException $exception) {
                return ApiResponse::error($exception->getMessage(), 422);
            }

            $servico->forceFill([
                'status' => StatusServico::Aprovado,
            ])->save();

            return ApiResponse::success([
                'servico' => [
                    'id' => $servico->id,
                    'status' => $servico->status->value,
                ],
                'payment' => [
                    'id' => $authorization->id,
                    'status' => $authorization->status->value,
                    'metodo' => $authorization->metodo->value,
                ],
            ]);
        });
    }

    private function authorizationForCapture(Servico $servico): ?PaymentAuthorization
    {
        $autorizado = $servico->authorizations()
            ->where('status', StatusPaymentAuthorization::Autorizado)
            ->latest('criado_em')
            ->first();

        if ($autorizado !== null) {
            return $autorizado;
        }

        return $servico->authorizations()
            ->where('status', StatusPaymentAuthorization::Capturado)
            ->latest('criado_em')
            ->first();
    }
}
