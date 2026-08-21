<?php

namespace App\Requests\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class RequestException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, code: $this->errorCode);
    }

    public static function precoTabelaAusente(): self
    {
        return new self(
            'Não há tabela de preço ativa para esta categoria e cidade.',
            422,
            'PRECO_TABELA_AUSENTE',
        );
    }
}
