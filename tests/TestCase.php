<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests;

use Ejoi8\MalaysiaPaymentGateway\PaymentGatewayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment
        $app['config']->set('payment-gateway.default', 'chip');
        
        $app['config']->set('payment-gateway.gateways.chip', [
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway::class,
            'brand_id' => 'test-brand-id',
            'secret_key' => 'test-secret-key',
            'sandbox' => true,
        ]);
        
        $app['config']->set('payment-gateway.gateways.toyyibpay', [
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ToyyibPayGateway::class,
            'secret_key' => 'test-secret-key',
            'category_code' => 'test-category',
            'sandbox' => true,
        ]);

        $app['config']->set('payment-gateway.gateways.manual_proof', [
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ManualProofGateway::class,
        ]);
        
        $app['config']->set('payment-gateway.gateways.stripe', [
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway::class,
            'public_key' => 'pk_test_xxx',
            'secret_key' => 'sk_test_xxx',
        ]);
        
        $app['config']->set('payment-gateway.gateways.paypal', [
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\PayPalGateway::class,
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'sandbox' => true,
        ]);
    }
}
