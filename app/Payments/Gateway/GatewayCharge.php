<?php

namespace App\Payments\Gateway;

use Carbon\CarbonImmutable;

final readonly class GatewayCharge
{
    public function __construct(
        public string $id,
        public string $status,
        public ?CarbonImmutable $expiresAt = null,
        public ?string $creditCardToken = null,
    ) {}
}
