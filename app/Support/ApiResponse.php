<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $pagination
     */
    public static function success(mixed $data = null, int $status = 200, ?array $pagination = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($pagination !== null) {
            $payload['pagination'] = $pagination;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
