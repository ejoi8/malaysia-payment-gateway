<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class EventsTest extends TestCase
{
    public function test_payment_initiated_event_is_fired(): void
    {
        Event::fake();

        Http::fake([
            'gate.chip-in.asia/*' => Http::response([
                'id' => 'txn_mock_123',
                'checkout_url' => 'https://mock-checkout-url.com',
            ], 200),
        ]);

        $manager = app(GatewayManager::class);
        $payable = new MockPayable();

        $manager->initiate('chip', $payable);

        Event::assertDispatched(PaymentInitiated::class, function ($event) use ($payable) {
            return $event->payable === $payable
                && $event->gateway === 'chip'
                && is_array($event->response);
        });
    }

    public function test_payment_succeeded_event_is_fired_on_verification(): void
    {
        Event::fake();

        $manager = app(GatewayManager::class);
        $payable = new MockPayable();

        $manager->verify('chip', $payable, [
            'status' => 'paid',
            'transaction_id' => 'txn_123',
        ]);

        Event::assertDispatched(PaymentSucceeded::class, function ($event) use ($payable) {
            return $event->payable === $payable
                && $event->gateway === 'chip'
                && $event->transactionId === 'txn_123';
        });
    }

    public function test_payment_failed_event_is_fired_on_failed_verification(): void
    {
        Event::fake();

        $manager = app(GatewayManager::class);
        $payable = new MockPayable();

        $manager->verify('chip', $payable, [
            'status' => 'failed',
            'error' => 'Declined',
        ]);

        Event::assertDispatched(PaymentFailed::class, function ($event) use ($payable) {
            return $event->payable === $payable
                && $event->gateway === 'chip';
        });
    }

    public function test_payment_initiated_event_has_correct_properties(): void
    {
        $payable = new MockPayable(reference: 'order-xyz');
        $response = ['type' => 'redirect', 'url' => 'https://example.com'];

        $event = new PaymentInitiated($payable, 'chip', $response);

        $this->assertSame($payable, $event->payable);
        $this->assertEquals('chip', $event->gateway);
        $this->assertEquals($response, $event->response);
    }

    public function test_payment_succeeded_event_has_correct_properties(): void
    {
        $payable = new MockPayable();
        $meta = ['amount' => 5000];

        $event = new PaymentSucceeded($payable, 'stripe', 'pi_123', $meta);

        $this->assertSame($payable, $event->payable);
        $this->assertEquals('stripe', $event->gateway);
        $this->assertEquals('pi_123', $event->transactionId);
        $this->assertEquals($meta, $event->meta);
    }

    public function test_payment_failed_event_has_correct_properties(): void
    {
        $payable = new MockPayable();
        $meta = ['reason' => 'insufficient_funds'];

        $event = new PaymentFailed($payable, 'paypal', 'Card declined', $meta);

        $this->assertSame($payable, $event->payable);
        $this->assertEquals('paypal', $event->gateway);
        $this->assertEquals('Card declined', $event->error);
        $this->assertEquals($meta, $event->meta);
    }

    public function test_payment_refunded_event_has_correct_properties(): void
    {
        $meta = ['refund_id' => 'ref_456'];

        $event = new PaymentRefunded('txn_123', 'chip', 5000, $meta);

        $this->assertEquals('txn_123', $event->transactionId);
        $this->assertEquals('chip', $event->gateway);
        $this->assertEquals(5000, $event->amount);
        $this->assertEquals($meta, $event->meta);
    }
}
