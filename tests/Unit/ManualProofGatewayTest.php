<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Gateways\ManualProofGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class ManualProofGatewayTest extends TestCase
{
    public function test_it_returns_gateway_name(): void
    {
        $gateway = new ManualProofGateway();
        
        $this->assertEquals('manual_proof', $gateway->getName());
    }

    public function test_it_does_not_support_webhooks(): void
    {
        $gateway = new ManualProofGateway();
        
        $this->assertFalse($gateway->supportsWebhooks());
    }

    public function test_it_does_not_support_refunds(): void
    {
        $gateway = new ManualProofGateway();
        
        $this->assertFalse($gateway->supportsRefunds());
    }

    public function test_it_returns_instructions_on_initiate(): void
    {
        $gateway = new ManualProofGateway();
        $payable = new MockPayable(
            settings: [
                'manual_proof_message' => 'Please transfer to account 1234567890',
                'bank_account_info' => 'Maybank: 1234567890',
            ]
        );

        $result = $gateway->initiate($payable);

        $this->assertEquals('instructions', $result['type']);
        $this->assertStringContainsString('1234567890', $result['message']);
        $this->assertStringContainsString('Maybank', $result['bank_info']);
    }

    public function test_it_includes_payment_details_in_instructions(): void
    {
        $gateway = new ManualProofGateway();
        $payable = new MockPayable(
            reference: 'booking-789',
            amount: 10000,
            currency: 'MYR'
        );

        $result = $gateway->initiate($payable);

        $this->assertEquals('booking-789', $result['reference']);
        $this->assertEquals(10000, $result['amount']);
        $this->assertEquals('MYR', $result['currency']);
    }

    public function test_it_uses_default_message_when_not_configured(): void
    {
        $gateway = new ManualProofGateway();
        $payable = new MockPayable(settings: []);

        $result = $gateway->initiate($payable);

        $this->assertStringContainsString('bank transfer', strtolower($result['message']));
    }

    public function test_it_verifies_approved_payment(): void
    {
        $gateway = new ManualProofGateway();
        $payable = new MockPayable(reference: 'order-123');

        $result = $gateway->verify($payable, ['approved' => true]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('manual-order-123', $result['transaction_id']);
        $this->assertArrayHasKey('verified_at', $result['meta']);
    }

    public function test_it_verifies_rejected_payment(): void
    {
        $gateway = new ManualProofGateway();
        $payable = new MockPayable();

        $result = $gateway->verify($payable, [
            'approved' => false,
            'rejection_reason' => 'Invalid receipt',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid receipt', $result['error']);
    }

    public function test_refund_returns_manual_required_message(): void
    {
        $gateway = new ManualProofGateway();

        $result = $gateway->refund('manual-123', 5000);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('manually', $result['error']);
    }
}
