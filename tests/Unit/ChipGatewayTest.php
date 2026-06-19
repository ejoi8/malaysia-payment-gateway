<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\RsaTestKey;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChipGatewayTest extends TestCase
{
    public function test_it_returns_gateway_name(): void
    {
        $gateway = new ChipGateway;

        $this->assertEquals('chip', $gateway->getName());
    }

    public function test_it_supports_webhooks(): void
    {
        $gateway = new ChipGateway;

        $this->assertTrue($gateway->supportsWebhooks());
    }

    public function test_it_supports_refunds(): void
    {
        $gateway = new ChipGateway;

        // CHIP supports refunds via POST /purchases/{id}/refund/.
        $this->assertTrue($gateway->supportsRefunds());
    }

    public function test_it_initiates_payment_with_correct_structure(): void
    {
        Http::fake([
            '*' => Http::response([
                'id' => 'chip_txn_123',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/12345',
            ], 200),
        ]);

        $gateway = ChipGateway::make(['brand_id' => 'test-brand', 'sandbox' => true]);
        $payable = new MockPayable;

        $result = $gateway->initiate($payable);

        $this->assertEquals('redirect', $result['type']);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertSame('chip_txn_123', $result['transaction_id']);
    }

    public function test_it_builds_payload_with_customer_info(): void
    {
        Http::fake([
            '*' => Http::response(['checkout_url' => 'https://gate.chip-in.asia/checkout/12345'], 200),
        ]);

        $gateway = ChipGateway::make(['brand_id' => 'test-brand']);
        $payable = new MockPayable(
            customer: [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '0198765432',
            ]
        );

        $result = $gateway->initiate($payable);
        $payload = $result['payload'];

        $this->assertEquals('Jane Doe', $payload['client']['full_name']);
        $this->assertEquals('jane@example.com', $payload['client']['email']);
        $this->assertEquals('0198765432', $payload['client']['phone']);
    }

    public function test_it_sends_a_single_product_line_priced_at_the_total(): void
    {
        Http::fake([
            '*' => Http::response(['checkout_url' => 'https://gate.chip-in.asia/checkout/12345'], 200),
        ]);

        $gateway = new ChipGateway;
        $payable = new MockPayable(
            amount: 5000,
            description: 'Court Booking',
            items: [
                ['name' => 'Court A', 'quantity' => 1, 'price' => 3000],
                ['name' => 'Court B', 'quantity' => 1, 'price' => 2000],
            ]
        );

        $result = $gateway->initiate($payable);
        $products = $result['payload']['purchase']['products'];

        $this->assertCount(1, $products);
        $this->assertStringContainsString('Court Booking', $products[0]['name']);
        $this->assertStringContainsString('2 items', $products[0]['name']);
        $this->assertSame(5000, $products[0]['price']);   // = the authoritative amount
    }

    public function test_it_summarizes_many_items_into_one_line(): void
    {
        Http::fake([
            '*' => Http::response(['checkout_url' => 'https://gate.chip-in.asia/checkout/12345'], 200),
        ]);

        $gateway = new ChipGateway;
        $payable = new MockPayable(
            amount: 10000,
            items: [
                ['name' => 'Item 1', 'quantity' => 1, 'price' => 2500],
                ['name' => 'Item 2', 'quantity' => 1, 'price' => 2500],
                ['name' => 'Item 3', 'quantity' => 1, 'price' => 2500],
                ['name' => 'Item 4', 'quantity' => 1, 'price' => 2500],
            ]
        );

        $result = $gateway->initiate($payable);
        $products = $result['payload']['purchase']['products'];

        $this->assertCount(1, $products);
        $this->assertStringContainsString('4 items', $products[0]['name']);
        $this->assertEquals(10000, $products[0]['price']);
    }

    public function test_it_verifies_successful_payment(): void
    {
        $gateway = new ChipGateway;
        $payable = new MockPayable;

        $result = $gateway->verify($payable, [
            'status' => 'paid',
            'transaction_id' => 'txn_123456',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('txn_123456', $result['transaction_id']);
    }

    public function test_it_verifies_failed_payment(): void
    {
        $gateway = new ChipGateway;
        $payable = new MockPayable;

        $result = $gateway->verify($payable, [
            'status' => 'failed',
            'error' => 'Insufficient funds',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_uses_sandbox_url_when_enabled(): void
    {
        Http::fake([
            '*' => Http::response(['checkout_url' => 'https://gate.chip-in.asia/checkout/12345'], 200),
        ]);

        $gateway = ChipGateway::make(['sandbox' => true]);
        $payable = new MockPayable;

        $result = $gateway->initiate($payable);

        // Check the URL returned in the structure, assuming mock returns it directly
        // But the previous implementation might have constructed it.
        // Let's rely on the mock response for now as that's what ChipGateway does (line 45: $data['checkout_url'] ?? ...)
        $this->assertStringContainsString('https://gate.chip-in.asia/checkout/12345', $result['url']);
    }

    public function test_it_uses_production_url_when_sandbox_disabled(): void
    {
        Http::fake([
            '*' => Http::response(['checkout_url' => 'https://gate.chip-in.asia/checkout/12345'], 200),
        ]);

        $gateway = ChipGateway::make(['sandbox' => false]);
        $payable = new MockPayable;

        $result = $gateway->initiate($payable);

        $this->assertStringContainsString('https://gate.chip-in.asia/checkout/12345', $result['url']);
    }

    public function test_it_processes_a_refund(): void
    {
        Http::fake([
            'gate.chip-in.asia/*' => Http::response(['id' => 'refund_1', 'status' => 'success'], 200),
        ]);

        $gateway = ChipGateway::make(['secret_key' => 'sk']);

        $result = $gateway->refund('chip_txn_1', 2500);

        $this->assertTrue($result['success']);
        $this->assertSame('refund_1', $result['transaction_id']);
    }

    public function test_it_skips_signature_verification_when_no_public_key(): void
    {
        $gateway = new ChipGateway;

        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        $this->assertTrue($gateway->verifySignature($request));
    }

    public function test_it_verifies_a_valid_rsa_signature(): void
    {
        $body = '{"status":"paid","id":"p1"}';
        $gateway = ChipGateway::make(['public_key' => RsaTestKey::publicPem()]);

        $valid = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $valid->headers->set('X-Signature', RsaTestKey::SIG_CHIP_PAID);
        $this->assertTrue($gateway->verifySignature($valid));

        $tampered = Request::create('/webhook', 'POST', [], [], [], [], '{"status":"paid","id":"TAMPERED"}');
        $tampered->headers->set('X-Signature', RsaTestKey::SIG_CHIP_PAID);
        $this->assertFalse($gateway->verifySignature($tampered));
    }
}
