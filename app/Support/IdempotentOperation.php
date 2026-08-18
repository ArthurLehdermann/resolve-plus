<?php

namespace App\Support;

use App\Auth\Models\Usuario;
use App\Services\Exceptions\ServiceException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdempotentOperation
{
    /**
     * @param  Closure(): JsonResponse  $action
     */
    public function run(Request $request, string $endpoint, Closure $action): JsonResponse
    {
        $chave = $this->requireKey($request);
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw ServiceException::forbidden('Não autenticado.');
        }

        $existing = IdempotencyKey::query()
            ->where('usuario_id', $usuario->id)
            ->where('chave', $chave)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing !== null) {
            return response()->json($existing->response_body, $existing->status_code);
        }

        $response = $action();
        /** @var array<string, mixed> $body */
        $body = $response->getData(true);

        try {
            IdempotencyKey::query()->create([
                'usuario_id' => $usuario->id,
                'chave' => $chave,
                'endpoint' => $endpoint,
                'status_code' => $response->getStatusCode(),
                'response_body' => $body,
            ]);
        } catch (UniqueConstraintViolationException) {
            $replay = IdempotencyKey::query()
                ->where('usuario_id', $usuario->id)
                ->where('chave', $chave)
                ->where('endpoint', $endpoint)
                ->firstOrFail();

            return response()->json($replay->response_body, $replay->status_code);
        }

        return $response;
    }

    private function requireKey(Request $request): string
    {
        $chave = $request->header('Idempotency-Key');

        if (! is_string($chave) || $chave === '') {
            throw ServiceException::unprocessable('Header Idempotency-Key é obrigatório.');
        }

        if (! Str::isUuid($chave)) {
            throw ServiceException::unprocessable('Idempotency-Key deve ser um UUID.');
        }

        return $chave;
    }
}
