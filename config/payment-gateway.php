<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | when a specific gateway is not specified.
    |
    */

    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'chip'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Configure each payment gateway with its credentials and settings.
    | To add a new gateway, create a class implementing GatewayInterface,
    | add it here with a 'driver_class' key, and you are done!
    |
    */

    'gateways' => [

        'chip' => [
            'enabled' => env('CHIP_ENABLED', true),
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ChipGateway::class,
            'brand_id' => env('CHIP_BRAND_ID'),
            'secret_key' => env('CHIP_SECRET_KEY'),
            'sandbox' => env('CHIP_SANDBOX', false),
            'currency' => env('CHIP_CURRENCY', 'MYR'),
            // PEM RSA public key used to verify webhook X-Signature headers.
            // Fetch it once from GET https://gate.chip-in.asia/api/v1/public_key/
            // When empty, webhook signature verification is skipped (logged).
            'public_key' => env('CHIP_PUBLIC_KEY'),
        ],

        'toyyibpay' => [
            'enabled' => env('TOYYIBPAY_ENABLED', true),
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ToyyibPayGateway::class,
            'secret_key' => env('TOYYIBPAY_SECRET_KEY'),
            'category_code' => env('TOYYIBPAY_CATEGORY_CODE'),
            'sandbox' => env('TOYYIBPAY_SANDBOX', false),
            'charge_customer' => env('TOYYIBPAY_CHARGE_CUSTOMER', 1),
            'expiry_days' => env('TOYYIBPAY_EXPIRY_DAYS', 3),
            'currency' => 'MYR', // ToyyibPay is MYR-only
        ],

        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\StripeGateway::class,
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'currency' => env('STRIPE_CURRENCY', 'MYR'),
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', true),
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\PayPalGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
            'currency' => env('PAYPAL_CURRENCY', 'MYR'),
            // Webhook id from the PayPal dashboard, used to verify webhook
            // signatures via the verify-webhook-signature API. When empty,
            // verification is skipped (logged).
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],

        'manual_proof' => [
            'enabled' => true,
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ManualProofGateway::class,
            'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'MYR'),
            'message' => env('MANUAL_PROOF_MESSAGE'),
            'bank_info' => env('MANUAL_PROOF_BANK_INFO'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    |
    | General settings that apply to all gateways.
    |
    */

    'settings' => [
        // Default currency for all gateways (can be overridden per gateway)
        'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'MYR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Line Item
    |--------------------------------------------------------------------------
    |
    | Gateways are always charged a SINGLE line at the payable's total amount —
    | so the gateway can never recompute or reject it (your full itemised list
    | stays in your own database). The line is named after the payable's
    | description; when the cart has more than one unit, an "(N items)" suffix is
    | appended so the gateway receipt still signals a multi-item purchase.
    |
    */

    'gateway_line' => [
        'append_count' => env('PAYMENT_APPEND_ITEM_COUNT', true),
        'label' => env('PAYMENT_ITEM_COUNT_LABEL', 'items'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP
    |--------------------------------------------------------------------------
    |
    | Timeouts applied to every gateway API call made through
    | AbstractGateway::http(). No automatic retry is performed because payment
    | creation is not idempotent.
    |
    */

    'http' => [
        'timeout' => env('PAYMENT_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('PAYMENT_HTTP_CONNECT_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox (Developer Utility)
    |--------------------------------------------------------------------------
    |
    | Enable the payment sandbox for testing gateway integrations.
    | WARNING: Should be disabled in production!
    |
    */

    'sandbox' => [
        'enabled' => env('PAYMENT_GATEWAY_SANDBOX', false),
        'prefix' => 'payment-gateway',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payable Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that implements PayableInterface.
    | Used for resolving webhooks and status checks.
    |
    */

    'model' => \Ejoi8\MalaysiaPaymentGateway\Models\Payment::class,

    /*
    |--------------------------------------------------------------------------
    | Persist Initiation Transaction Id
    |--------------------------------------------------------------------------
    |
    | Save the gateway's transaction id onto the payable at initiation time
    | (without changing its status), so a pending payment can be reconciled or
    | polled before it is confirmed. The verified id overwrites it on success.
    |
    */

    'persist_initiation_id' => env('PAYMENT_PERSIST_INITIATION_ID', true),

    /*
    |--------------------------------------------------------------------------
    | Guard Against Re-charging a Paid Payment
    |--------------------------------------------------------------------------
    |
    | When enabled, initiate() throws PaymentAlreadyProcessedException if the
    | payable is already in a success status — preventing a second charge from a
    | double-clicked button or a stale checkout link. Failed/pending payments may
    | still be (re)initiated.
    |
    */

    'guard_paid_initiation' => env('PAYMENT_GUARD_PAID_INITIATION', true),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configuration for payment routes (webhooks, status page).
    |
    */

    'routes' => [
        'prefix' => 'payment',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-Payment Redirects
    |--------------------------------------------------------------------------
    |
    | Where to send the customer once the package has finished verifying the
    | payment on their return. Leave null to use the built-in status page
    | (/payment/status/{reference}). The reference is appended as ?reference=...,
    | or substituted into a {reference} placeholder in the URL. Per-payment
    | overrides win: set metadata.urls.success_redirect / .failed_redirect.
    |
    */

    'redirects' => [
        'success' => env('PAYMENT_SUCCESS_URL'),
        'failed' => env('PAYMENT_FAILED_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback Handling
    |--------------------------------------------------------------------------
    |
    | Controls how incoming gateway callbacks are processed.
    | Stale callbacks can be acknowledged but ignored to avoid applying
    | late gateway updates long after the payment should have expired.
    |
    */

    'callbacks' => [
        'max_age_minutes' => env('PAYMENT_CALLBACK_MAX_AGE_MINUTES', 10),

        // Serialize concurrent callbacks for the same payment (via an atomic
        // cache lock) so two simultaneous webhooks can't both process it.
        // Degrades gracefully if the cache store doesn't support locks.
        'lock' => env('PAYMENT_CALLBACK_LOCK', true),
        'lock_seconds' => env('PAYMENT_CALLBACK_LOCK_SECONDS', 10),
        'lock_wait' => env('PAYMENT_CALLBACK_LOCK_WAIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Check Portal (Customer Facing)
    |--------------------------------------------------------------------------
    |
    | Configuration for the public "Track My Payment" page.
    |
    */

    'status_portal' => [
        'enabled' => true,
        'path' => 'check-status', // e.g., /payment/check-status
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Enable email notifications for payment events.
    |
    */

    'notifications' => [
        'enabled' => env('PAYMENT_NOTIFICATIONS_ENABLED', true),

        // Use queue for sending emails (recommended for production)
        // Set to true if you have a queue worker running
        'queue' => env('PAYMENT_NOTIFICATIONS_QUEUE', false),

        // Which customer emails to send
        'email_success' => true,
        'email_failure' => true,
        'email_initiated' => true,

        // Merchant/admin recipient(s) notified on success & failure.
        // Comma-separate for multiple addresses. Leave empty to disable.
        'admin_email' => env('PAYMENT_ADMIN_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing Webhook
    |--------------------------------------------------------------------------
    |
    | Forward verified payment events (succeeded, failed, refunded) to your own
    | backend as a signed server-to-server POST — the same way gateways notify
    | this package. When a secret is set, requests carry an
    | `X-Payment-Signature: hmac_sha256(rawBody, secret)` header so the receiver
    | can verify authenticity. Leave the URL empty to disable.
    |
    */

    'outgoing_webhook' => [
        'url' => env('MERCHANT_WEBHOOK_URL'),
        'secret' => env('MERCHANT_WEBHOOK_SECRET'),
        'queue' => env('MERCHANT_WEBHOOK_QUEUE', false),
    ],

];
