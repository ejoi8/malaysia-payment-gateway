<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class StripeGatewayTest extends TestCase
{
    public function test_it_returns_gateway_name(): void
    {
        $gateway = new StripeGateway();
        
        $this->assertEquals('stripe', $gateway->getName());
    }

    public function test_it_supports_webhooks(): void
    {
        $gateway = new StripeGateway();
        
        $this->assertTrue($gateway->supportsWebhooks());
    }

    public function test_it_supports_refunds(): void
    {
        $gateway = new StripeGateway();
        
        $this->assertTrue($gateway->supportsRefunds());
    }

    public function test_it_verifies_checkout_session_completed(): void
    {
        $gateway = new StripeGateway();
        $payable = new MockPayable();

        $result = $gateway->verify($payable, [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_intent' => 'pi_test_456',
                    'payment_status' => 'paid',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_456', $result['transaction_id']);
    }

    public function test_it_verifies_payment_intent_succeeded(): void
    {
        $gateway = new StripeGateway();
        $payable = new MockPayable();

        $result = $gateway->verify($payable, [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_789',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_789', $result['transaction_id']);
    }

    public function test_it_returns_failure_for_unknown_event(): void
    {
        $gateway = new StripeGateway();
        $payable = new MockPayable();

        $result = $gateway->verify($payable, [
            'type' => 'unknown.event',
        ]);

        $this->assertFalse($result['success']);
    }
}
