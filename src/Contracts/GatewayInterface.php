<?php

namespace Ejoi8\MalaysiaPaymentGateway\Contracts;

use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;

/**
 * Contract for payment gateway implementations.
 *
 * Each gateway (Chip, ToyyibPay, Stripe, etc.) implements this interface.
 */
interface GatewayInterface
{
    /**
     * Get the gateway identifier.
     * Example: 'chip', 'toyyibpay', 'stripe'
     */
    public function getName(): string;

    /**
     * Get the gateway type (how it handles verification).
     *
     * - WEBHOOK: Verification via POST webhook, GET just redirects user
     * - API: Verification via API call on GET return (e.g., Stripe session_id)
     * - MANUAL: No automated verification, requires manual approval
     */
    public function getType(): GatewayType;

    /**
     * Initiate a payment for the given payable entity.
     *
     * @param  PayableInterface  $payable  The entity being paid for
     * @return array Response with type and data:
     *               - ['type' => 'redirect', 'url' => '...'] for redirect-based
     *               - ['type' => 'client_secret', 'secret' => '...'] for Stripe Elements
     *               - ['type' => 'instructions', 'message' => '...'] for manual proof
     */
    public function initiate(PayableInterface $payable): array;

    /**
     * Verify a payment callback/webhook.
     *
     * @param  PayableInterface  $payable  The entity that was paid for
     * @param  array  $payload  The callback/webhook data
     * @return array Verification result:
     *               - ['success' => true, 'transaction_id' => '...', 'meta' => [...]]
     *               - ['success' => false, 'error' => '...']
     */
    public function verify(PayableInterface $payable, array $payload): array;

    /**
     * Check if gateway supports webhooks.
     */
    public function supportsWebhooks(): bool;

    /**
     * Check if gateway supports refunds.
     */
    public function supportsRefunds(): bool;

    /**
     * Process a refund (if supported).
     *
     * @param  string  $transactionId  The original transaction ID
     * @param  int  $amount  Amount to refund in cents (null = full refund)
     * @return array Refund result
     */
    public function refund(string $transactionId, ?int $amount = null): array;

    /**
     * Verify webhook signature (if applicable).
     *
     * @param  \Illuminate\Http\Request  $request  The incoming request
     * @return bool True if signature is valid
     */
    public function verifySignature(\Illuminate\Http\Request $request): bool;

    /**
     * Extract the payment reference ID from the webhook request.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming request
     * @return string|null The payment reference ID
     */
    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string;

    /**
     * Check the current status of a payment with the gateway.
     *
     * @param  PayableInterface  $payable  The entity to check status for
     * @return array Status result with 'status' and 'message' keys
     */
    public function checkStatus(PayableInterface $payable): array;

    /**
     * Factory method to create gateway instance from config.
     *
     * @param  array  $config  Gateway configuration
     * @return static
     */
    public static function make(array $config): self;
}

