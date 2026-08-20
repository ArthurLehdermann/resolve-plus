<?php

namespace App\Payments\Http;

use App\Http\Controllers\Controller;
use App\Payments\Webhooks\HandleAsaasWebhook;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, HandleAsaasWebhook $handle): JsonResponse
    {
        $configurado = (string) config('payments.asaas.webhook_token');
        $recebido = (string) $request->header('asaas-access-token', '');

        if ($configurado === '' || ! hash_equals($configurado, $recebido)) {
            return ApiResponse::error('Token inválido.', 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->json()->all();

        ($handle)($payload);

        return ApiResponse::success(['recebido' => true]);
    }
}
