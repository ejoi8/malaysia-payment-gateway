<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Models\Payment;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class PaymentModelTest extends TestCase
{
    public function test_get_payment_urls_merges_metadata_over_defaults(): void
    {
        $payment = new Payment([
            'gateway' => 'chip',
            'metadata' => ['urls' => ['success_redirect' => 'https://shop.test/done']],
        ]);

        $urls = $payment->getPaymentUrls();

        // Per-payment override is present...
        $this->assertSame('https://shop.test/done', $urls['success_redirect']);
        // ...and the gateway return/callback URLs are NOT dropped.
        $this->assertStringContainsString('webhook/chip', $urls['return_url']);
        $this->assertStringContainsString('webhook/chip', $urls['callback_url']);
    }

    public function test_get_payment_urls_returns_defaults_without_metadata(): void
    {
        $payment = new Payment(['gateway' => 'toyyibpay']);

        $urls = $payment->getPaymentUrls();

        $this->assertStringContainsString('webhook/toyyibpay', $urls['return_url']);
        $this->assertArrayNotHasKey('success_redirect', $urls);
    }
}
