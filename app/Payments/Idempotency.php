<?php

namespace App\Payments;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class Idempotency
{
    public function remember(Request $request, string $escopo, callable $callback): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            return ApiResponse::error('Idempotency-Key é obrigatório.', 422);
        }

        $usuarioId = $request->user()?->id;

        $existing = IdempotencyKey::query()
            ->where('usuario_id', $usuarioId)
            ->where('chave', $key)
            ->where('escopo', $escopo)
            ->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->response_status);
        }

        /** @var JsonResponse $response */
        $response = $callback();

        $payload = json_decode((string) $response->getContent(), true);

        IdempotencyKey::query()->create([
            'usuario_id' => $usuarioId,
            'chave' => $key,
            'escopo' => $escopo,
            'response_status' => $response->status(),
            'response_body' => is_array($payload) ? $payload : ['raw' => $response->getContent()],
        ]);

        return $response;
    }
}
