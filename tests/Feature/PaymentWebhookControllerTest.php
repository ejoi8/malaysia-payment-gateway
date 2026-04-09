<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestEloquentPayable;
use Illuminate\Support\Carbon;

class PaymentWebhookControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestEloquentPayable::resetRegistry();
        config(['payment-gateway.model' => TestEloquentPayable::class]);
    }

    protected function tearDown(): void
    {
        TestEloquentPayable::resetRegistry();

        parent::tearDown();
    }

    public function test_it_ignores_stale_post_callbacks_older_than_ten_minutes(): void
    {
        TestEloquentPayable::register(new TestEloquentPayable([
            'reference' => 'stale-ref-123',
            'status' => PaymentStatus::defaultPendingStatus(),
            'gateway' => 'chip',
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Stale callback payment',
            'created_at' => Carbon::now()->subMinutes(11),
        ]));

        $response = $this->postJson(route('payment-gateway.webhook', ['driver' => 'chip']), [
            'reference' => 'stale-ref-123',
            'status' => 'paid',
            'transaction_id' => 'txn-stale-123',
        ]);

        $payable = TestEloquentPayable::findByReference('stale-ref-123');

        $response->assertOk()->assertJson([
            'success' => true,
            'ignored' => true,
        ]);

        $this->assertSame(PaymentStatus::defaultPendingStatus(), $payable->status);
        $this->assertNull($payable->transaction_id);
        $this->assertFalse($payable->wasSaved);
    }

    public function test_it_processes_recent_post_callbacks_within_the_allowed_window(): void
    {
        TestEloquentPayable::register(new TestEloquentPayable([
            'reference' => 'fresh-ref-123',
            'status' => PaymentStatus::defaultPendingStatus(),
            'gateway' => 'chip',
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Fresh callback payment',
            'created_at' => Carbon::now()->subMinutes(9),
        ]));

        $response = $this->postJson(route('payment-gateway.webhook', ['driver' => 'chip']), [
            'reference' => 'fresh-ref-123',
            'status' => 'paid',
            'transaction_id' => 'txn-fresh-123',
        ]);

        $payable = TestEloquentPayable::findByReference('fresh-ref-123');

        $response->assertOk()->assertJson([
            'success' => true,
            'message' => 'Payment successful',
        ]);

        $this->assertSame(PaymentStatus::defaultSuccessStatus(), $payable->status);
        $this->assertSame('txn-fresh-123', $payable->transaction_id);
        $this->assertTrue($payable->wasSaved);
    }
}
