<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ToyyibPayGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestEloquentPayable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class CheckStatusTest extends TestCase
{
    public function test_chip_check_status_reports_paid(): void
    {
        Http::fake(['gate.chip-in.asia/*' => Http::response(['status' => 'paid'], 200)]);

        $result = ChipGateway::make([])->checkStatus(
            new TestEloquentPayable(['reference' => 'r', 'transaction_id' => 'chip_1'])
        );

        $this->assertSame(PaymentStatus::PAID->value, $result['status']);
        $this->assertSame('chip_1', $result['transaction_id']);
    }

    public function test_chip_check_status_is_pending_without_a_stored_id(): void
    {
        $result = ChipGateway::make([])->checkStatus(new TestEloquentPayable(['reference' => 'r']));

        $this->assertSame(PaymentStatus::PENDING->value, $result['status']);
    }

    public function test_toyyibpay_check_status_reports_paid(): void
    {
        Http::fake(['*getBillTransactions*' => Http::response([['billpaymentStatus' => '1']], 200)]);

        $result = ToyyibPayGateway::make([])->checkStatus(
            new TestEloquentPayable(['reference' => 'r', 'transaction_id' => 'BILL1'])
        );

        $this->assertSame(PaymentStatus::PAID->value, $result['status']);
    }

    public function test_stripe_check_status_reports_paid(): void
    {
        Http::fake(['*checkout/sessions*' => Http::response([
            'payment_status' => 'paid',
            'payment_intent' => 'pi_9',
        ], 200)]);

        $result = StripeGateway::make([])->checkStatus(
            new TestEloquentPayable(['reference' => 'r', 'transaction_id' => 'cs_1'])
        );

        $this->assertSame(PaymentStatus::PAID->value, $result['status']);
        $this->assertSame('pi_9', $result['transaction_id']);
    }

    public function test_reconcile_fires_succeeded_when_gateway_reports_paid(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::fake(['gate.chip-in.asia/*' => Http::response(['status' => 'paid'], 200)]);

        $payable = new TestEloquentPayable(['reference' => 'r', 'transaction_id' => 'chip_1', 'gateway' => 'chip']);

        $status = app(GatewayManager::class)->reconcile('chip', $payable);

        $this->assertSame(PaymentStatus::PAID->value, $status);
        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_reconcile_leaves_an_unresolved_payment_pending(): void
    {
        Event::fake([PaymentSucceeded::class]);
        Http::fake(['gate.chip-in.asia/*' => Http::response(['status' => 'created'], 200)]);

        $payable = new TestEloquentPayable(['reference' => 'r', 'transaction_id' => 'chip_1', 'gateway' => 'chip']);

        $status = app(GatewayManager::class)->reconcile('chip', $payable);

        $this->assertSame(PaymentStatus::PENDING->value, $status);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }
}
