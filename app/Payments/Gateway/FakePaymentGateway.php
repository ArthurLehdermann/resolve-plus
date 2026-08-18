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

    public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge
    {
        $id = 'pay_fake_'.Str::uuid();
        $this->charges[] = [
            'id' => $id,
            'customerId' => $customerId,
            'amount' => $amountCents,
            'type' => 'CREDIT_CARD',
        ];

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

        return new GatewayCharge(
            id: $id,
            status: 'CONFIRMED',
        );
    }

    public function cancel(string $gatewayPaymentId): void
    {
        $this->cancels[] = $gatewayPaymentId;
    }

    public function transfer(string $walletId, int $amountCents): string
    {
        $this->transfers[] = ['walletId' => $walletId, 'amount' => $amountCents];

        return 'tr_fake_'.Str::uuid();
    }
}
