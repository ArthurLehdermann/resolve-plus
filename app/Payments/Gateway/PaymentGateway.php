<?php

namespace App\Payments\Gateway;

interface PaymentGateway
{
    public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge;

    /**
     * @param  list<array{walletId: string, percentualValue: float}>  $splits
     */
    public function capture(string $gatewayPaymentId, int $amountCents, array $splits = []): GatewayCharge;

    public function chargePix(string $customerId, int $amountCents): GatewayCharge;

    public function cancel(string $gatewayPaymentId): void;

    public function transfer(string $walletId, int $amountCents): string;
}
