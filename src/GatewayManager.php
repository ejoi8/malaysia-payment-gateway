<?php

namespace Ejoi8\MalaysiaPaymentGateway;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use InvalidArgumentException;

/**
 * Gateway Manager - resolves and manages payment gateway instances.
 *
 * Usage:
 *   $manager = app(GatewayManager::class);
 *   $gateway = $manager->driver('chip');
 *   $response = $gateway->initiate($booking);
 */
class GatewayManager
{
    protected array $drivers = [];

    protected array $customDrivers = [];

    /**
     * Get a gateway driver instance.
     */
    public function driver(?string $name = null): GatewayInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Initiate payment and fire event.
     */
    public function initiate(string $driver, PayableInterface $payable): array
    {
        $gateway = $this->driver($driver);

        $response = $gateway->initiate($payable);

        event(new PaymentInitiated($payable, $driver, $response));

        return $response;
    }

    /**
     * Verify payment and fire appropriate event.
     */
    public function verify(string $driver, PayableInterface $payable, array $payload): array
    {
        $gateway = $this->driver($driver);
        $result = $gateway->verify($payable, $payload);

        if ($result['success']) {
            event(new PaymentSucceeded(
                $payable,
                $driver,
                $result['transaction_id'] ?? '',
                $result['meta'] ?? []
            ));
        } else {
            event(new PaymentFailed(
                $payable,
                $driver,
                $result['error'] ?? 'Unknown error',
                $result['meta'] ?? []
            ));
        }

        return $result;
    }

    /**
     * Process refund and fire event.
     */
    public function refund(string $driver, string $transactionId, ?int $amount = null): array
    {
        $gateway = $this->driver($driver);

        if (! $gateway->supportsRefunds()) {
            return [
                'success' => false,
                'error' => "Gateway '{$driver}' does not support refunds",
            ];
        }

        $result = $gateway->refund($transactionId, $amount);

        if ($result['success']) {
            event(new PaymentRefunded($transactionId, $driver, $amount, $result['meta'] ?? []));
        }

        return $result;
    }

    /**
     * Register a custom gateway driver.
     * 
     * key usage scenarios:
     * 1. Runtime Overrides: Swapping credentials dynamically (e.g., Sandbox with user input).
     * 2. Testing: Injecting a mock/fake gateway instead of the real one.
     * 3. Multi-Tenancy: Configuring a gateway with tenant-specific API keys.
     * 
     * @param string $name The driver name (e.g. 'chip', 'stripe')
     * @param callable $callback A closure that returns the Gateway instance
     */
    public function extend(string $name, callable $callback): static
    {
        $this->customDrivers[$name] = $callback;

        return $this;
    }

    /**
     * Get all available driver names.
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->customDrivers);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return config('payment-gateway.default', 'chip');
    }

    /**
     * Resolve a gateway instance by name.
     */
    protected function resolve(string $name): GatewayInterface
    {
        if (isset($this->customDrivers[$name])) {
            return call_user_func($this->customDrivers[$name], app());
        }

        throw new InvalidArgumentException("Payment gateway [{$name}] is not supported.");
    }

    /**
     * Check the status of a payment directly with the gateway.
     */
    public function checkStatus(string $driver, PayableInterface $payable): array
    {
        return $this->driver($driver)->checkStatus($payable);
    }
}
