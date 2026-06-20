<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentAdminMail;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Mail;

class AdminNotificationTest extends TestCase
{
    public function test_admin_is_emailed_on_success_when_configured(): void
    {
        Mail::fake();
        config(['payment-gateway.notifications.admin_email' => 'admin@store.test']);

        event(new PaymentSucceeded(new MockPayable(reference: 'ord-1'), 'chip', 'txn_1'));

        Mail::assertSent(PaymentAdminMail::class, function (PaymentAdminMail $mail) {
            return $mail->hasTo('admin@store.test') && $mail->status === 'paid';
        });
    }

    public function test_admin_is_emailed_on_failure_with_reason(): void
    {
        Mail::fake();
        config(['payment-gateway.notifications.admin_email' => 'admin@store.test']);

        event(new PaymentFailed(new MockPayable(), 'chip', 'Insufficient funds'));

        Mail::assertSent(PaymentAdminMail::class, function (PaymentAdminMail $mail) {
            return $mail->status === 'failed' && $mail->reason === 'Insufficient funds';
        });
    }

    public function test_admin_supports_multiple_comma_separated_recipients(): void
    {
        Mail::fake();
        config(['payment-gateway.notifications.admin_email' => 'a@store.test, b@store.test']);

        event(new PaymentSucceeded(new MockPayable(), 'chip', 'txn_1'));

        Mail::assertSent(PaymentAdminMail::class, function (PaymentAdminMail $mail) {
            return $mail->hasTo('a@store.test') && $mail->hasTo('b@store.test');
        });
    }

    public function test_admin_is_not_emailed_when_not_configured(): void
    {
        Mail::fake();
        config(['payment-gateway.notifications.admin_email' => null]);

        event(new PaymentSucceeded(new MockPayable(), 'chip', 'txn_1'));

        Mail::assertNotSent(PaymentAdminMail::class);
    }
}
