<?php

namespace App\Payments;

use Illuminate\Http\JsonResponse;

class IdempotencyGuard
{
    /**
     * @param  callable(): JsonResponse  $callback
     */
    public function remember(string $key, string $usuarioId, string $rota, callable $callback): JsonResponse
    {
        $existing = IdempotencyKey::query()
            ->where('usuario_id', $usuarioId)
            ->where('chave', $key)
            ->where('escopo', $rota)
            ->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->response_status);
        }

        $response = $callback();

        $payload = json_decode((string) $response->getContent(), true);

        IdempotencyKey::query()->create([
            'usuario_id' => $usuarioId,
            'chave' => $key,
            'escopo' => $rota,
            'response_status' => $response->status(),
            'response_body' => is_array($payload) ? $payload : ['raw' => $response->getContent()],
        ]);

        return $response;
    }
}
