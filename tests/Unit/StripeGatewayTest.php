<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

    public function test_it_initiates_payment_with_normalized_transaction_id(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ], 200),
        ]);

        $gateway = new StripeGateway();
        $payable = new MockPayable();

        $result = $gateway->initiate($payable);

        $this->assertSame('redirect', $result['type']);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $result['url']);
        $this->assertSame('cs_test_123', $result['session_id']);
        $this->assertSame('cs_test_123', $result['transaction_id']);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('response', $result);
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

    public function test_it_charges_the_payable_amount_as_a_single_line(): void
    {
        Http::fake([
            '*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/x'], 200),
        ]);

        $gateway = new StripeGateway();
        // Items would sum to RM100, but the real charge is RM90 (a discount).
        $payable = new MockPayable(amount: 9000, description: 'Order', items: [
            ['name' => 'Shirt', 'quantity' => 1, 'price' => 5000],
            ['name' => 'Pants', 'quantity' => 1, 'price' => 5000],
        ]);

        $payload = $gateway->initiate($payable)['payload'];

        $this->assertSame(9000, $payload['line_items[0][price_data][unit_amount]']);
        $this->assertSame(1, $payload['line_items[0][quantity]']);
        $this->assertArrayNotHasKey('line_items[1][quantity]', $payload);
        $this->assertStringContainsString('2 items', $payload['line_items[0][price_data][product_data][name]']);
    }

    public function test_it_skips_webhook_verification_when_no_secret_configured(): void
    {
        config(['payment-gateway.gateways.stripe.webhook_secret' => null]);
        $gateway = new StripeGateway();

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        $this->assertTrue($gateway->verifySignature($request));
    }

    public function test_it_verifies_a_valid_stripe_signature(): void
    {
        $secret = 'whsec_test';
        config(['payment-gateway.gateways.stripe.webhook_secret' => $secret]);
        $gateway = new StripeGateway();

        $body = '{"id":"evt_1"}';
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $this->assertTrue($gateway->verifySignature($request));
    }

    public function test_it_rejects_an_invalid_stripe_signature(): void
    {
        config(['payment-gateway.gateways.stripe.webhook_secret' => 'whsec_test']);
        $gateway = new StripeGateway();

        $body = '{"id":"evt_1"}';
        $timestamp = time();

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1=deadbeef");

        $this->assertFalse($gateway->verifySignature($request));
    }
}
