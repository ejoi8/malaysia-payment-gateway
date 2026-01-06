<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\PayPalGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

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

    public function test_it_uses_sandbox_url_when_enabled(): void
    {
        $gateway = new PayPalGateway(sandbox: true);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('getApiUrl');
        $method->setAccessible(true);

        $url = $method->invoke($gateway);

        $this->assertStringContainsString('sandbox', $url);
    }

    public function test_it_uses_production_url_when_sandbox_disabled(): void
    {
        $gateway = new PayPalGateway(sandbox: false);

        $reflection = new \ReflectionClass($gateway);
        $method = $reflection->getMethod('getApiUrl');
        $method->setAccessible(true);

        $url = $method->invoke($gateway);

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
