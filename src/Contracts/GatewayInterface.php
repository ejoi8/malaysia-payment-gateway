<?php

namespace Ejoi8\MalaysiaPaymentGateway\Contracts;

use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;

/**
 * Contract for payment gateway implementations.
 *
 * Each gateway (Chip, ToyyibPay, Stripe, etc.) implements this interface.
 * Most gateways should extend {@see \Ejoi8\MalaysiaPaymentGateway\Gateways\AbstractGateway},
 * which provides shared config resolution, response builders and sensible
 * defaults for the optional capability methods below.
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
     * @return PaymentResponse Normalized initiation result. Exposes typed
     *                         accessors (isRedirect(), redirectUrl(), ...) and,
     *                         for backward compatibility, array access to the
     *                         normalized keys ('type', 'payload', 'transaction_id',
     *                         'url', 'response') plus any gateway-specific extras
     *                         such as 'session_id', 'order_id' or manual
     *                         instruction fields.
     */
    public function initiate(PayableInterface $payable): PaymentResponse;

    /**
     * Verify a payment callback/webhook.
     *
     * @param  PayableInterface  $payable  The entity that was paid for
     * @param  array  $payload  The callback/webhook data
     * @return VerificationResult Verification result. Exposes typed properties
     *                           ($result->success, $result->transactionId,
     *                           $result->error, $result->meta) and array access
     *                           for backward compatibility.
     */
    public function verify(PayableInterface $payable, array $payload): VerificationResult;

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
     * @return VerificationResult Refund result (success/error + meta).
     */
    public function refund(string $transactionId, ?int $amount = null): VerificationResult;

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

