<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Listeners\PersistInitiationId;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestEloquentPayable;

class PersistInitiationIdTest extends TestCase
{
    public function test_it_persists_the_initiation_transaction_id_without_changing_status(): void
    {
        $payable = new TestEloquentPayable([
            'reference' => 'ref-i',
            'status' => PaymentStatus::defaultPendingStatus(),
        ]);

        (new PersistInitiationId)->handle(new PaymentInitiated($payable, 'chip', ['transaction_id' => 'init-1']));

        $this->assertSame('init-1', $payable->getAttribute('transaction_id'));
        $this->assertTrue($payable->wasSaved);
        $this->assertSame(PaymentStatus::defaultPendingStatus(), $payable->status);
    }

    public function test_it_does_not_overwrite_an_existing_transaction_id(): void
    {
        $payable = new TestEloquentPayable([
            'reference' => 'ref-i2',
            'transaction_id' => 'existing',
            'status' => PaymentStatus::defaultPendingStatus(),
        ]);

        (new PersistInitiationId)->handle(new PaymentInitiated($payable, 'chip', ['transaction_id' => 'init-2']));

        $this->assertSame('existing', $payable->getAttribute('transaction_id'));
        $this->assertFalse($payable->wasSaved);
    }

    public function test_it_can_be_disabled_via_config(): void
    {
        config(['payment-gateway.persist_initiation_id' => false]);

        $payable = new TestEloquentPayable(['reference' => 'ref-i3']);

        (new PersistInitiationId)->handle(new PaymentInitiated($payable, 'chip', ['transaction_id' => 'init-3']));

        $this->assertNull($payable->getAttribute('transaction_id'));
        $this->assertFalse($payable->wasSaved);
    }
}
