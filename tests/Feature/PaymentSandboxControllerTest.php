<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\PaymentSandboxController;
use Ejoi8\MalaysiaPaymentGateway\Tests\SandboxCustomPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class PaymentSandboxControllerTest extends TestCase
{
    public function test_sandbox_uses_configured_payable_model(): void
    {
        config(['payment-gateway.model' => SandboxCustomPayable::class]);

        $controller = new class extends PaymentSandboxController
        {
            public function exposeBuildPayable($request, string $gateway)
            {
                return $this->buildPayable($request, $gateway);
            }
        };

        $request = request()->create('/payment-gateway/sandbox', 'POST', [
            'gateway' => 'chip',
            'payable_type' => 'simple',
            'simple_amount' => '10.00',
            'simple_description' => 'Sandbox Payment',
            'customer_name' => 'Sandbox User',
            'customer_email' => 'sandbox@example.com',
            'customer_phone' => '+60123456789',
        ]);

        $payable = $controller->exposeBuildPayable($request, 'chip');

        $this->assertInstanceOf(SandboxCustomPayable::class, $payable);
        $this->assertSame('chip', $payable->gateway);
        $this->assertSame('Sandbox Payment', $payable->getPaymentDescription());
        $this->assertSame(1000, $payable->getPaymentAmount());
    }
}
