<?php

namespace App\Services\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class ServiceException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status);
    }

    public static function forbidden(string $message = 'Sem permissão.'): self
    {
        return new self($message, 403);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function unprocessable(string $message): self
    {
        return new self($message, 422);
    }
}
