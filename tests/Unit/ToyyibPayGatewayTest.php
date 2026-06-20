<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\ToyyibPayGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ToyyibPayGatewayTest extends TestCase
{
    public function test_it_returns_gateway_name(): void
    {
        $gateway = new ToyyibPayGateway();
        
        $this->assertEquals('toyyibpay', $gateway->getName());
    }

    public function test_it_supports_webhooks(): void
    {
        $gateway = new ToyyibPayGateway();
        
        $this->assertTrue($gateway->supportsWebhooks());
    }

    public function test_it_does_not_support_refunds(): void
    {
        $gateway = new ToyyibPayGateway();
        
        $this->assertFalse($gateway->supportsRefunds());
    }

    public function test_it_initiates_payment_with_correct_structure(): void
    {
        Http::fake([
            '*' => Http::response([['BillCode' => 'BILL001']], 200),
        ]);

        $gateway = ToyyibPayGateway::make(['secret_key' => 'test-key', 'category_code' => 'test-cat']);
        $payable = new MockPayable();

        $result = $gateway->initiate($payable);

        $this->assertEquals('redirect', $result['type']);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertSame('BILL001', $result['transaction_id']);
    }

    public function test_it_builds_payload_with_bill_info(): void
    {
        Http::fake([
            '*' => Http::response([['BillCode' => 'BILL001']], 200),
        ]);

        $gateway = new ToyyibPayGateway();
        $payable = new MockPayable(
            reference: 'order-456',
            amount: 7500,
            description: 'Court Booking',
            customer: [
                'name' => 'Ahmad',
                'email' => 'ahmad@example.com',
                'phone' => '0123456789',
            ]
        );

        $result = $gateway->initiate($payable);
        $payload = $result['payload'];

        $this->assertEquals('order-456', $payload['billName']);
        $this->assertEquals('Court Booking', $payload['billDescription']);
        $this->assertEquals(7500, $payload['billAmount']);
        $this->assertEquals('Ahmad', $payload['billTo']);
        $this->assertEquals('ahmad@example.com', $payload['billEmail']);
    }

    public function test_it_bills_the_reference_and_total_regardless_of_items(): void
    {
        Http::fake([
            '*' => Http::response([['BillCode' => 'BILL001']], 200),
        ]);

        $gateway = new ToyyibPayGateway();
        $payable = new MockPayable(
            reference: 'order-789',
            amount: 5000,
            items: [
                ['name' => 'Item 1', 'quantity' => 1, 'price' => 2000],
                ['name' => 'Item 2', 'quantity' => 1, 'price' => 3000],
            ]
        );

        $result = $gateway->initiate($payable);

        // ToyyibPay has no line items — one bill, named by reference, at the total.
        $this->assertSame('order-789', $result['payload']['billName']);
        $this->assertSame(5000, $result['payload']['billAmount']);
    }

    public function test_it_verifies_successful_payment(): void
    {
        $gateway = new ToyyibPayGateway();
        $payable = new MockPayable();

        // ToyyibPay uses status_id: 1 = success
        $result = $gateway->verify($payable, [
            'status_id' => '1',
            'billcode' => 'BILL123',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('BILL123', $result['transaction_id']);
    }

    public function test_it_verifies_failed_payment(): void
    {
        $gateway = new ToyyibPayGateway();
        $payable = new MockPayable();

        // status_id: 3 = failed
        $result = $gateway->verify($payable, [
            'status_id' => '3',
            'msg' => 'Payment declined',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment declined', $result['error']);
    }

    public function test_it_uses_sandbox_url_when_enabled(): void
    {
        Http::fake([
            '*' => Http::response([['BillCode' => 'BILL001']], 200),
        ]);

        $gateway = ToyyibPayGateway::make(['sandbox' => true]);
        $payable = new MockPayable();

        $result = $gateway->initiate($payable);

        $this->assertStringContainsString('dev.toyyibpay', $result['url']);
    }

    public function test_refund_returns_unsupported_error(): void
    {
        $gateway = new ToyyibPayGateway();

        $result = $gateway->refund('txn_123', 5000);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not support', $result['error']);
    }

    public function test_it_verifies_a_valid_callback_signature(): void
    {
        $gateway = ToyyibPayGateway::make(['secret_key' => 'sekret']);
        $hash = md5('sekret'.'1'.'order-99'.'refno-1'.'ok');

        $request = Request::create('/callback', 'POST', [
            'status' => '1',
            'order_id' => 'order-99',
            'refno' => 'refno-1',
            'hash' => $hash,
        ]);

        $this->assertTrue($gateway->verifySignature($request));
    }

    public function test_it_rejects_a_forged_callback_signature(): void
    {
        $gateway = ToyyibPayGateway::make(['secret_key' => 'sekret']);

        $request = Request::create('/callback', 'POST', [
            'status' => '1',
            'order_id' => 'order-99',
            'refno' => 'refno-1',
            'hash' => 'deadbeef',
        ]);

        $this->assertFalse($gateway->verifySignature($request));
    }

    public function test_it_skips_verification_when_no_hash_is_present(): void
    {
        $gateway = ToyyibPayGateway::make(['secret_key' => 'sekret']);

        // The browser GET return carries no hash — nothing to verify.
        $request = Request::create('/return', 'GET', ['status_id' => '1', 'order_id' => 'order-99']);

        $this->assertTrue($gateway->verifySignature($request));
    }
}
