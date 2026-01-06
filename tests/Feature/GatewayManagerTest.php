<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ManualProofGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\PayPalGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway;
use Ejoi8\MalaysiaPaymentGateway\Gateways\ToyyibPayGateway;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use InvalidArgumentException;

class GatewayManagerTest extends TestCase
{
    public function test_it_can_be_resolved_from_container(): void
    {
        $manager = app(GatewayManager::class);

        $this->assertInstanceOf(GatewayManager::class, $manager);
    }

    public function test_it_resolves_chip_gateway(): void
    {
        $manager = app(GatewayManager::class);

        $gateway = $manager->driver('chip');

        $this->assertInstanceOf(ChipGateway::class, $gateway);
    }

    public function test_it_resolves_toyyibpay_gateway(): void
    {
        $manager = app(GatewayManager::class);

        $gateway = $manager->driver('toyyibpay');

        $this->assertInstanceOf(ToyyibPayGateway::class, $gateway);
    }

    public function test_it_resolves_manual_proof_gateway(): void
    {
        $manager = app(GatewayManager::class);

        $gateway = $manager->driver('manual_proof');

        $this->assertInstanceOf(ManualProofGateway::class, $gateway);
    }

    public function test_it_resolves_stripe_gateway(): void
    {
        $manager = app(GatewayManager::class);

        $gateway = $manager->driver('stripe');

        $this->assertInstanceOf(StripeGateway::class, $gateway);
    }

    public function test_it_resolves_paypal_gateway(): void
    {
        $manager = app(GatewayManager::class);

        $gateway = $manager->driver('paypal');

        $this->assertInstanceOf(PayPalGateway::class, $gateway);
    }

    public function test_it_throws_for_unsupported_gateway(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported');

        $manager = app(GatewayManager::class);
        $manager->driver('unsupported');
    }

    public function test_it_caches_gateway_instances(): void
    {
        $manager = app(GatewayManager::class);

        $gateway1 = $manager->driver('chip');
        $gateway2 = $manager->driver('chip');

        $this->assertSame($gateway1, $gateway2);
    }

    public function test_it_lists_available_drivers(): void
    {
        $manager = app(GatewayManager::class);

        $drivers = $manager->getAvailableDrivers();

        $this->assertContains('chip', $drivers);
        $this->assertContains('toyyibpay', $drivers);
        $this->assertContains('manual_proof', $drivers);
        $this->assertContains('stripe', $drivers);
        $this->assertContains('paypal', $drivers);
    }

    public function test_it_allows_extending_with_custom_driver(): void
    {
        $manager = app(GatewayManager::class);

        $manager->extend('custom', function ($app) {
            return new ManualProofGateway(); // Use existing gateway for testing
        });

        $drivers = $manager->getAvailableDrivers();

        $this->assertContains('custom', $drivers);
    }

    public function test_extended_driver_can_be_resolved(): void
    {
        $manager = app(GatewayManager::class);

        $manager->extend('senangpay', function ($app) {
            return new ManualProofGateway();
        });

        $gateway = $manager->driver('senangpay');

        $this->assertInstanceOf(ManualProofGateway::class, $gateway);
    }

    public function test_it_returns_default_driver(): void
    {
        config(['payment-gateway.default' => 'toyyibpay']);

        $manager = app(GatewayManager::class);

        $this->assertEquals('toyyibpay', $manager->getDefaultDriver());
    }
}
