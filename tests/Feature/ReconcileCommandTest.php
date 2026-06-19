<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Models\Payment;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_it_reconciles_a_stuck_pending_payment(): void
    {
        config(['payment-gateway.model' => Payment::class]);
        Mail::fake();
        Http::fake(['gate.chip-in.asia/*' => Http::response(['status' => 'paid'], 200)]);

        $payment = Payment::create([
            'reference' => 'STUCK-1',
            'status' => 'pending',
            'gateway' => 'chip',
            'transaction_id' => 'chip_purchase_1',
            'amount' => 5000,
            'currency' => 'MYR',
            'description' => 'Stuck payment',
            'customer_email' => 'buyer@example.com',
        ]);
        // Make it older than the reconcile cutoff.
        $payment->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $this->artisan('payment:reconcile', ['--minutes' => 15])->assertSuccessful();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_it_skips_payments_younger_than_the_cutoff(): void
    {
        config(['payment-gateway.model' => Payment::class]);
        Http::fake(['gate.chip-in.asia/*' => Http::response(['status' => 'paid'], 200)]);

        $payment = Payment::create([
            'reference' => 'RECENT-1',
            'status' => 'pending',
            'gateway' => 'chip',
            'transaction_id' => 'chip_2',
            'amount' => 5000,
            'currency' => 'MYR',
            'description' => 'Recent payment',
        ]);
        // Just created — younger than the 15-minute cutoff.

        $this->artisan('payment:reconcile', ['--minutes' => 15])->assertSuccessful();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertNothingSent();
    }
}
