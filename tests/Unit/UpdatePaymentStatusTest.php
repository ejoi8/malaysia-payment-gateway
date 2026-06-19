<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Listeners\UpdatePaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestEloquentPayable;

class UpdatePaymentStatusTest extends TestCase
{
    public function test_it_updates_any_eloquent_payable_on_success(): void
    {
        $payable = new TestEloquentPayable([
            'reference' => 'ref-123',
            'status' => PaymentStatus::defaultPendingStatus(),
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Test Payment',
        ]);

        $listener = new UpdatePaymentStatus;
        $listener->handle(new PaymentSucceeded($payable, 'chip', 'txn-123'));

        $this->assertTrue($payable->wasSaved);
        $this->assertSame(PaymentStatus::defaultSuccessStatus(), $payable->status);
        $this->assertSame('txn-123', $payable->transaction_id);
    }

    public function test_it_uses_custom_update_hook_when_available(): void
    {
        $payable = new class implements PayableInterface
        {
            public array $updates = [];

            public function getPaymentReference(): string
            {
                return 'ref-456';
            }

            public function getPaymentAmount(): int
            {
                return 1000;
            }

            public function getPaymentCurrency(): string
            {
                return 'MYR';
            }

            public function getPaymentCustomer(): array
            {
                return [];
            }

            public function getPaymentSettings(): array
            {
                return [];
            }

            public function getPaymentUrls(): array
            {
                return [];
            }

            public function getPaymentItems(): array
            {
                return [];
            }

            public function getPaymentDescription(): string
            {
                return 'Test Payment';
            }

            public static function findByReference(string $reference): ?self
            {
                return null;
            }

            public function applyPaymentGatewayUpdate(array $attributes): void
            {
                $this->updates[] = $attributes;
            }
        };

        $listener = new UpdatePaymentStatus;
        $listener->handle(new PaymentFailed($payable, 'chip', 'Declined'));

        $this->assertSame([[
            'status' => PaymentStatus::defaultFailedStatus(),
            'metadata' => ['failure_reason' => 'Declined'],
        ]], $payable->updates);
    }

    public function test_it_marks_payable_refunded_by_transaction_id(): void
    {
        TestEloquentPayable::resetRegistry();
        config(['payment-gateway.model' => TestEloquentPayable::class]);

        $payable = TestEloquentPayable::register(new TestEloquentPayable([
            'reference' => 'ref-refund',
            'transaction_id' => 'txn-refund-1',
            'status' => PaymentStatus::defaultSuccessStatus(),
            'amount' => 2500,
            'currency' => 'MYR',
            'description' => 'Refundable',
        ]));

        $listener = new UpdatePaymentStatus;
        $listener->handle(new PaymentRefunded('txn-refund-1', 'chip', 2500, ['refund_id' => 'r1']));

        $this->assertTrue($payable->wasSaved);
        $this->assertSame(PaymentStatus::REFUNDED->value, $payable->status);

        TestEloquentPayable::resetRegistry();
    }

    public function test_refund_is_a_noop_when_transaction_id_is_unknown(): void
    {
        TestEloquentPayable::resetRegistry();
        config(['payment-gateway.model' => TestEloquentPayable::class]);

        $listener = new UpdatePaymentStatus;

        // No matching payable registered — must not throw.
        $listener->handle(new PaymentRefunded('txn-missing', 'chip', 1000));

        $this->assertNull(TestEloquentPayable::findByTransactionId('txn-missing'));

        TestEloquentPayable::resetRegistry();
    }
}
