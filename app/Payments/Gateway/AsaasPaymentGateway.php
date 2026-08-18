<?php

namespace App\Payments\Gateway;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasPaymentGateway implements PaymentGateway
{
    public function authorizeCard(string $customerId, int $amountCents, string $creditCardToken): GatewayCharge
    {
        $data = $this->request()->post('/v3/payments', [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $this->reais($amountCents),
            'dueDate' => now()->toDateString(),
            'creditCardToken' => $creditCardToken,
            'authorizeOnly' => true,
            'remoteIp' => (string) config('payments.asaas.fallback_remote_ip', '127.0.0.1'),
        ])->json();

        $this->assertOk($data, 'Falha ao autorizar cartão no Asaas.');

        $expiresAt = isset($data['authorizedUntil'])
            ? CarbonImmutable::parse((string) $data['authorizedUntil'])
            : CarbonImmutable::now()->addDays((int) config('payments.authorization_days', 3));

        return new GatewayCharge(
            id: (string) $data['id'],
            status: (string) ($data['status'] ?? 'AUTHORIZED'),
            expiresAt: $expiresAt,
            creditCardToken: $data['creditCardToken'] ?? $creditCardToken,
        );
    }

    public function capture(string $gatewayPaymentId, int $amountCents, array $splits = []): GatewayCharge
    {
        if ($splits !== []) {
            $update = $this->request()->put('/v3/payments/'.$gatewayPaymentId, [
                'splits' => $splits,
            ]);

            if ($update->failed()) {
                throw new GatewayException('Falha ao atualizar splits no Asaas.');
            }
        }

        $data = $this->request()->post('/v3/payments/'.$gatewayPaymentId.'/captureAuthorizedPayment', [
            'value' => $this->reais($amountCents),
        ])->json();

        $this->assertOk($data, 'Falha ao capturar pagamento no Asaas.');

        return new GatewayCharge(
            id: (string) ($data['id'] ?? $gatewayPaymentId),
            status: (string) ($data['status'] ?? 'CONFIRMED'),
        );
    }

    public function chargePix(string $customerId, int $amountCents): GatewayCharge
    {
        $data = $this->request()->post('/v3/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $this->reais($amountCents),
            'dueDate' => now()->toDateString(),
        ])->json();

        $this->assertOk($data, 'Falha ao cobrar Pix no Asaas.');

        return new GatewayCharge(
            id: (string) $data['id'],
            status: (string) ($data['status'] ?? 'PENDING'),
        );
    }

    public function cancel(string $gatewayPaymentId): void
    {
        $response = $this->request()->delete('/v3/payments/'.$gatewayPaymentId);

        if ($response->failed() && $response->status() !== 404) {
            throw new GatewayException('Falha ao cancelar autorização no Asaas.');
        }
    }

    public function transfer(string $walletId, int $amountCents): string
    {
        $data = $this->request()->post('/v3/transfers', [
            'walletId' => $walletId,
            'value' => $this->reais($amountCents),
        ])->json();

        $this->assertOk($data, 'Falha ao transferir no Asaas.');

        return (string) $data['id'];
    }

    private function request(): PendingRequest
    {
        $apiKey = (string) config('services.asaas.api_key');

        if ($apiKey === '') {
            throw new GatewayException('ASAAS_API_KEY não configurada.');
        }

        return Http::baseUrl((string) config('services.asaas.base_url'))
            ->timeout(15)
            ->acceptJson()
            ->withHeaders([
                'access_token' => $apiKey,
            ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function assertOk(?array $data, string $message): void
    {
        if ($data === null || isset($data['errors']) || ! isset($data['id'])) {
            throw new GatewayException($message);
        }
    }

    private function reais(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
