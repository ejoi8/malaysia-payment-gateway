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
        ],

        'manual_proof' => [
            'enabled' => true,
            'driver_class' => \Ejoi8\MalaysiaPaymentGateway\Gateways\ManualProofGateway::class,
            'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'MYR'),
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

        // Maximum line items before aggregation
        'max_items' => env('PAYMENT_MAX_ITEMS', 10),
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

        // Which emails to send
        'email_success' => true,
        'email_failure' => true,
        'email_initiated' => true,
    ],

];
