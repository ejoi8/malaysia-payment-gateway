<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class OutgoingWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent the customer/admin mailers from attempting real delivery.
        Mail::fake();
    }

    public function test_it_posts_a_signed_webhook_on_success(): void
    {
        Http::fake(['merchant.test/*' => Http::response('', 200)]);
        config([
            'payment-gateway.outgoing_webhook.url' => 'https://merchant.test/hook',
            'payment-gateway.outgoing_webhook.secret' => 'sekret',
        ]);

        event(new PaymentSucceeded(new MockPayable(reference: 'ord-1', amount: 5000, currency: 'MYR'), 'chip', 'txn_1'));

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);

            return $request->url() === 'https://merchant.test/hook'
                && $request->method() === 'POST'
                && $data['event'] === 'payment.succeeded'
                && $data['reference'] === 'ord-1'
                && $data['amount'] === 5000
                && $data['transaction_id'] === 'txn_1'
                && $request->hasHeader('X-Payment-Signature', hash_hmac('sha256', $request->body(), 'sekret'));
        });
    }

    public function test_it_posts_on_refund(): void
    {
        Http::fake(['merchant.test/*' => Http::response('', 200)]);
        config(['payment-gateway.outgoing_webhook.url' => 'https://merchant.test/hook']);

        event(new PaymentRefunded('txn_9', 'chip', 2500, ['refund_id' => 'r1']));

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);

            return $data['event'] === 'payment.refunded'
                && $data['transaction_id'] === 'txn_9'
                && $data['amount'] === 2500;
        });
    }

    public function test_it_omits_signature_header_when_no_secret(): void
    {
        Http::fake(['merchant.test/*' => Http::response('', 200)]);
        config([
            'payment-gateway.outgoing_webhook.url' => 'https://merchant.test/hook',
            'payment-gateway.outgoing_webhook.secret' => null,
        ]);

        event(new PaymentSucceeded(new MockPayable(), 'chip', 'txn_1'));

        Http::assertSent(fn ($request) => ! $request->hasHeader('X-Payment-Signature'));
    }

    public function test_it_does_not_post_when_no_url_configured(): void
    {
        Http::fake();
        config(['payment-gateway.outgoing_webhook.url' => null]);

        event(new PaymentRefunded('txn_1', 'chip', 5000, []));

        Http::assertNothingSent();
    }
}
