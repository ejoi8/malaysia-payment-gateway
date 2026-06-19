<?php

namespace Ejoi8\MalaysiaPaymentGateway;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Exceptions\PaymentAlreadyProcessedException;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Illuminate\Database\Eloquent\Model;
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

    protected array $driverResolvers = [];

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
     * Alias for driver(): resolve a gateway instance by name.
     *
     * Reads naturally through the Payment facade, e.g.
     * Payment::gateway('chip')->initiate($payable).
     */
    public function gateway(?string $name = null): GatewayInterface
    {
        return $this->driver($name);
    }

    /**
     * Initiate payment and fire event.
     */
    public function initiate(string $driver, PayableInterface $payable): PaymentResponse
    {
        $this->guardAgainstReinitiatingPaid($payable);

        $response = $this->driver($driver)->initiate($payable);

        // The event carries a plain array so listeners (including queued ones)
        // stay simple and serialization-safe.
        event(new PaymentInitiated($payable, $driver, $response->toArray()));

        return $response;
    }

    /**
     * Verify payment and fire appropriate event.
     */
    public function verify(string $driver, PayableInterface $payable, array $payload): VerificationResult
    {
        $result = $this->driver($driver)->verify($payable, $payload);

        if ($result->success) {
            event(new PaymentSucceeded($payable, $driver, $result->transactionId ?? '', $result->meta));
        } else {
            event(new PaymentFailed($payable, $driver, $result->error ?? 'Unknown error', $result->meta));
        }

        return $result;
    }

    /**
     * Process refund and fire event.
     */
    public function refund(string $driver, string $transactionId, ?int $amount = null): VerificationResult
    {
        $gateway = $this->driver($driver);

        if (! $gateway->supportsRefunds()) {
            return VerificationResult::failure("Gateway '{$driver}' does not support refunds");
        }

        $result = $gateway->refund($transactionId, $amount);

        if ($result->success) {
            event(new PaymentRefunded($transactionId, $driver, $amount, $result->meta));
        }

        return $result;
    }

    /**
     * Refuse to initiate a payment that has already succeeded, to avoid a
     * second charge (double-clicked button, stale link). Failed/pending
     * payments may be (re)initiated. Skipped for payables that don't expose a
     * status, or when disabled via config.
     */
    protected function guardAgainstReinitiatingPaid(PayableInterface $payable): void
    {
        if (! config('payment-gateway.guard_paid_initiation', true)) {
            return;
        }

        $status = $payable instanceof Model
            ? $payable->getAttribute('status')
            : ($payable->status ?? null);

        if (is_string($status) && PaymentStatus::isSuccess($status)) {
            throw new PaymentAlreadyProcessedException(
                "Payment [{$payable->getPaymentReference()}] has already been paid (status: {$status})."
            );
        }
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
        $this->driverResolvers[$name] = $callback;
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * Get all available driver names.
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->driverResolvers);
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
        if (isset($this->driverResolvers[$name])) {
            return call_user_func($this->driverResolvers[$name], app());
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

    /**
     * Poll the gateway for a payment's real status and, if it has resolved,
     * fire the matching event (so a missed webhook is recovered). Returns the
     * resolved status string.
     */
    public function reconcile(string $driver, PayableInterface $payable): string
    {
        $result = $this->driver($driver)->checkStatus($payable);
        $status = $result['status'] ?? PaymentStatus::PENDING->value;

        if (PaymentStatus::isSuccess($status)) {
            event(new PaymentSucceeded($payable, $driver, $result['transaction_id'] ?? '', $result));
        } elseif (PaymentStatus::isFailed($status)) {
            event(new PaymentFailed($payable, $driver, $result['message'] ?? 'Payment failed', $result));
        }

        return $status;
    }
}
