<?php

namespace App\Payments\Gateway;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGateway
{
    /** @var list<array{id: string, customerId: string, amount: int, type: string}> */
    public array $charges = [];

    /** @var list<array{id: string, amount: int, splits: list<array{walletId: string, percentualValue: float}>}> */
    public array $captures = [];

    /** @var list<string> */
    public array $cancels = [];

    /** @var list<array{walletId: string, amount: int}> */
    public array $transfers = [];

    /**
     * Status "de verdade" no gateway, consultado por find(). Testes que
     * simulam o Asaas já ter recebido o pagamento (ex.: corrida do N9)
     * escrevem aqui antes de rodar o job: $gateway->statuses[$id] = 'RECEIVED'.
     * Sem entrada, assume PENDING - id nunca visto por este fake ainda não
     * foi confirmado nem cancelado.
     *
     * @var array<string, string>
     */
    public array $statuses = [];

    public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge
    {
        $id = 'pay_fake_'.Str::uuid();
        $this->charges[] = [
            'id' => $id,
            'customerId' => $customerId,
            'amount' => $amountCents,
            'type' => 'CREDIT_CARD',
        ];
        $this->statuses[$id] = 'AUTHORIZED';

        return new GatewayCharge(
            id: $id,
            status: 'AUTHORIZED',
            expiresAt: CarbonImmutable::now()->addDays((int) config('payments.authorization_days', 3)),
            creditCardToken: $creditCardToken,
        );
    }

    public function capture(string $gatewayPaymentId, int $amountCents, array $splits = []): GatewayCharge
    {
        $this->captures[] = [
            'id' => $gatewayPaymentId,
            'amount' => $amountCents,
            'splits' => $splits,
        ];
        $this->statuses[$gatewayPaymentId] = 'CONFIRMED';

        return new GatewayCharge(
            id: $gatewayPaymentId,
            status: 'CONFIRMED',
        );
    }

    public function chargePix(string $customerId, int $amountCents): GatewayCharge
    {
        $id = 'pay_fake_pix_'.Str::uuid();
        $this->charges[] = [
            'id' => $id,
            'customerId' => $customerId,
            'amount' => $amountCents,
            'type' => 'PIX',
        ];
        $this->statuses[$id] = 'PENDING';

        // PENDING, igual ao Asaas real: a cobrança Pix não confirma na
        // hora, só via webhook. Um teste que precise do caminho já
        // confirmado deve simular isso explicitamente (troca de gateway
        // ou POST no webhook), não confiar num fake otimista.
        return new GatewayCharge(
            id: $id,
            status: 'PENDING',
        );
    }

    public function find(string $gatewayPaymentId): GatewayCharge
    {
        return new GatewayCharge(
            id: $gatewayPaymentId,
            status: $this->statuses[$gatewayPaymentId] ?? 'PENDING',
        );
    }

    public function cancel(string $gatewayPaymentId): void
    {
        $this->cancels[] = $gatewayPaymentId;
        $this->statuses[$gatewayPaymentId] = 'CANCELLED';
    }

    public function transfer(string $walletId, int $amountCents): string
    {
        $this->transfers[] = ['walletId' => $walletId, 'amount' => $amountCents];

        return 'tr_fake_'.Str::uuid();
    }
}
