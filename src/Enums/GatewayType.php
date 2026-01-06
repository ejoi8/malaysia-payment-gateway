<?php

namespace Ejoi8\MalaysiaPaymentGateway\Enums;

/**
 * Gateway Type Enum
 *
 * Defines how gateways handle payment verification.
 * Each gateway declares its type, eliminating hardcoded gateway name lists.
 */
enum GatewayType: string
{
    /**
     * Webhook-based gateways.
     *
     * Payment verification happens via POST webhook from the gateway.
     * GET returns (user redirect) just show status page - no verification needed.
     *
     * Examples: CHIP, ToyyibPay
     */
    case WEBHOOK = 'webhook';

    /**
     * API-based gateways.
     *
     * Payment verification can happen on GET return by calling the gateway's API.
     * GET returns contain data (session_id, token) needed for API verification.
     *
     * Examples: Stripe, PayPal
     */
    case API = 'api';

    /**
     * Manual gateways.
     *
     * No automated verification - requires manual approval.
     *
     * Examples: Manual Proof (bank transfer, cash)
     */
    case MANUAL = 'manual';

    /**
     * Check if this gateway type requires verification on GET return.
     */
    public function requiresGetVerification(): bool
    {
        return $this === self::API;
    }

    /**
     * Check if this gateway type uses webhooks.
     */
    public function usesWebhook(): bool
    {
        return $this === self::WEBHOOK;
    }
}
