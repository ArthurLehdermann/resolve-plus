<?php

namespace App\Requests;

final class PricingResult
{
    public function __construct(
        public readonly int $min,
        public readonly int $max,
        public readonly int $fatorBp,
        public readonly string $tabelaPrecoId,
    ) {}
}
