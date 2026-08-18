<?php

namespace App\Payments;

use RuntimeException;

class PaymentDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 409,
    ) {
        parent::__construct($message);
    }
}
