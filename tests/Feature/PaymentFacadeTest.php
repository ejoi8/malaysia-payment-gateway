<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Facades\Payment;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class PaymentFacadeTest extends TestCase
{
    public function test_facade_resolves_the_manager(): void
    {
        $this->assertInstanceOf(GatewayManager::class, Payment::getFacadeRoot());
    }

    public function test_facade_gateway_helper_returns_a_driver(): void
    {
        $this->assertInstanceOf(ChipGateway::class, Payment::gateway('chip'));
    }

    public function test_facade_initiate_returns_a_payment_response(): void
    {
        Http::fake([
            'gate.chip-in.asia/*' => Http::response([
                'id' => 'chip_1',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/chip_1',
            ], 200),
        ]);

        $result = Payment::initiate('chip', new MockPayable());

        $this->assertInstanceOf(PaymentResponse::class, $result);
        $this->assertTrue($result->isRedirect());
        $this->assertSame('chip_1', $result['transaction_id']);
    }
}
