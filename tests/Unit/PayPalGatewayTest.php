<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\PayPalGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class PayPalGatewayTest extends TestCase
{
    public function test_it_returns_gateway_name(): void
    {
        $gateway = new PayPalGateway;

        $this->assertEquals('paypal', $gateway->getName());
    }

    public function test_it_supports_webhooks(): void
    {
        $gateway = new PayPalGateway;

        $this->assertTrue($gateway->supportsWebhooks());
    }

    public function test_it_supports_refunds(): void
    {
        $gateway = new PayPalGateway;

        $this->assertTrue($gateway->supportsRefunds());
    }

    public function test_it_initiates_payment_with_normalized_transaction_id(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDER-123',
                'links' => [
                    [
                        'rel' => 'approve',
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123',
                    ],
                ],
            ], 200),
        ]);

        $gateway = new PayPalGateway(sandbox: true);
        $payable = new MockPayable;

        $result = $gateway->initiate($payable);

        $this->assertSame('redirect', $result['type']);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-123', $result['url']);
        $this->assertSame('PAYPAL-ORDER-123', $result['order_id']);
        $this->assertSame('PAYPAL-ORDER-123', $result['transaction_id']);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('response', $result);
    }

    public function test_it_uses_sandbox_url_when_enabled(): void
    {
        $gateway = new class(null, null, true) extends PayPalGateway
        {
            public function apiUrl(): string
            {
                return $this->getApiUrl();
            }
        };

        $url = $gateway->apiUrl();

        $this->assertStringContainsString('sandbox', $url);
    }

    public function test_it_uses_production_url_when_sandbox_disabled(): void
    {
        $gateway = new class(null, null, false) extends PayPalGateway
        {
            public function apiUrl(): string
            {
                return $this->getApiUrl();
            }
        };

        $url = $gateway->apiUrl();

        $this->assertStringNotContainsString('sandbox', $url);
        $this->assertStringContainsString('api-m.paypal.com', $url);
    }

    public function test_verify_fails_without_order_id(): void
    {
        $gateway = new PayPalGateway;
        $payable = new MockPayable;

        $result = $gateway->verify($payable, []);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('order ID', $result['error']);
    }
}
