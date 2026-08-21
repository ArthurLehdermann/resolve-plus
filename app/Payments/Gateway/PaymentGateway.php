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

    /**
     * Consulta o status atual no gateway, sem efeito colateral. Usado antes
     * de decisões irreversíveis sobre uma cobrança já criada (ex.: expirar
     * um Pix pendente) - status local pode estar desatualizado se o webhook
     * ainda não chegou (N9).
     */
    public function find(string $gatewayPaymentId): GatewayCharge;

    public function cancel(string $gatewayPaymentId): void;

    public function transfer(string $walletId, int $amountCents): string;
}
