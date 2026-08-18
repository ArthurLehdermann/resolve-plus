<?php

namespace Tests\Unit\Payments;

use App\Payments\Gateway\AsaasPaymentGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasPaymentGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.asaas.api_key', 'test-asaas-key');
        config()->set('services.asaas.base_url', 'https://api-sandbox.asaas.com');
    }

    public function test_authorize_card_posts_authorize_only_charge(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'AUTHORIZED',
                'authorizedUntil' => '2026-08-21T12:00:00Z',
            ], 200),
        ]);

        $charge = app(AsaasPaymentGateway::class)->authorizeCard(
            'cus_1',
            10_000,
            'tok_abc',
        );

        $this->assertSame('pay_123', $charge->id);
        $this->assertSame('AUTHORIZED', $charge->status);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api-sandbox.asaas.com/v3/payments'
                && $request->method() === 'POST'
                && $request['authorizeOnly'] === true
                && $request['billingType'] === 'CREDIT_CARD'
                && $request['creditCardToken'] === 'tok_abc'
                && $request['value'] === 100.0
                && $request->hasHeader('access_token', 'test-asaas-key');
        });
    }

    public function test_capture_updates_splits_then_captures(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments/pay_123' => Http::response(['id' => 'pay_123'], 200),
            'https://api-sandbox.asaas.com/v3/payments/pay_123/captureAuthorizedPayment' => Http::response([
                'id' => 'pay_123',
                'status' => 'RECEIVED',
            ], 200),
        ]);

        $charge = app(AsaasPaymentGateway::class)->capture('pay_123', 10_000, [
            ['walletId' => 'wal_pro', 'percentualValue' => 85.0],
        ]);

        $this->assertSame('RECEIVED', $charge->status);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && str_ends_with($request->url(), '/v3/payments/pay_123')
                && $request['splits'][0]['walletId'] === 'wal_pro';
        });

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/v3/payments/pay_123/captureAuthorizedPayment')
                && $request['value'] === 100.0;
        });
    }

    public function test_pix_charge_does_not_send_splits(): void
    {
        Http::fake([
            'https://api-sandbox.asaas.com/v3/payments' => Http::response([
                'id' => 'pay_pix',
                'status' => 'PENDING',
            ], 200),
        ]);

        $charge = app(AsaasPaymentGateway::class)->chargePix('cus_1', 2500);

        $this->assertSame('pay_pix', $charge->id);

        Http::assertSent(function ($request): bool {
            return $request['billingType'] === 'PIX'
                && $request['value'] === 25.0
                && ! isset($request['splits'])
                && ! isset($request['authorizeOnly']);
        });
    }
}
