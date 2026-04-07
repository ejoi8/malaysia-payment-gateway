# Malaysia Payment Gateway

A Laravel package for payment gateway integrations. Supports Malaysian gateways (CHIP, ToyyibPay) plus international gateways (Stripe, PayPal) and a manual proof flow.

## Features

✅ **Multiple Gateways** - CHIP, ToyyibPay, Stripe, PayPal, Manual Proof  
✅ **Unified API** - Same interface for all gateways  
✅ **Unified Callback Routes** - Built-in handling for gateway POST callbacks and GET returns  
✅ **Return URL Handling** - Unified callback for both webhooks and user returns  
✅ **Status Portal** - Customer-facing payment tracking based on stored payment state  
✅ **Email Notifications** - Automatic payment receipts  
✅ **Custom Payable Models** - Use the built-in `Payment` model or your own model
✅ **Developer Sandbox** - Test gateways without writing code

> **Current state:** this package is published on Packagist as `ejoi8/malaysia-payment-gateway`, with source hosted at `https://github.com/ejoi8/malaysia-payment-gateway`. Live `checkStatus()` polling is still stubbed for CHIP, ToyyibPay, Stripe, and PayPal, and webhook signature verification still needs production hardening for CHIP, ToyyibPay, and PayPal. Stripe signature verification works when `STRIPE_WEBHOOK_SECRET` is configured.

---

## Quick Start

### 1. Installation

```bash
composer require ejoi8/malaysia-payment-gateway
php artisan migrate
```

Package links:

- Packagist: `https://packagist.org/packages/ejoi8/malaysia-payment-gateway`
- GitHub: `https://github.com/ejoi8/malaysia-payment-gateway`

### 2. Configure `.env`

```env
# Default Gateway
PAYMENT_GATEWAY_DEFAULT=chip

# CHIP (Malaysian FPX)
CHIP_BRAND_ID=your-brand-id
CHIP_SECRET_KEY=your-secret-key
CHIP_SANDBOX=true

# ToyyibPay (Malaysian FPX)
TOYYIBPAY_SECRET_KEY=your-secret-key
TOYYIBPAY_CATEGORY_CODE=your-category-code
TOYYIBPAY_SANDBOX=true

# Stripe
STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# PayPal
PAYPAL_CLIENT_ID=your-client-id
PAYPAL_CLIENT_SECRET=your-client-secret
PAYPAL_SANDBOX=true

# Currency Configuration (optional - defaults to MYR)
PAYMENT_DEFAULT_CURRENCY=MYR
CHIP_CURRENCY=MYR
STRIPE_CURRENCY=MYR
PAYPAL_CURRENCY=MYR

# Notifications / sandbox helpers
PAYMENT_NOTIFICATIONS_ENABLED=true
PAYMENT_NOTIFICATIONS_QUEUE=false
PAYMENT_GATEWAY_SANDBOX=false

# Manual proof (optional)
MANUAL_PROOF_MESSAGE="Please transfer and send your receipt"
MANUAL_PROOF_BANK_INFO="Maybank 1234567890"
```

> **Note:** ToyyibPay is MYR-only. Other gateways can be configured to use different currencies.

### 3. Create a Payment

```php
use Ejoi8\MalaysiaPaymentGateway\Models\Payment;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;

class CheckoutController extends Controller
{
    public function store(Request $request, GatewayManager $gateway)
    {
        // 1. Create payment record
        $payment = Payment::create([
            'gateway' => 'chip',
            'reference' => 'ORD-' . uniqid(),
            'amount' => 5000, // RM 50.00 (in cents)
            'currency' => 'MYR',
            'description' => 'Court Booking',
            'customer_name' => 'Ali',
            'customer_email' => 'ali@example.com',
            'items' => [
                ['name' => 'Badminton Court', 'quantity' => 1, 'price' => 5000]
            ],
        ]);

        // 2. Initiate payment
        $response = $gateway->initiate('chip', $payment);

        // 3. Redirect to gateway
        if ($response['type'] === 'redirect') {
            return redirect($response['url']);
        }

        return back()->with('error', $response['error'] ?? 'Payment failed');
    }
}
```

**That's it!** The package handles everything else automatically.

---

## How It Works

### Payment Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         PAYMENT FLOW                                 │
└─────────────────────────────────────────────────────────────────────┘

1. INITIATE PAYMENT
   ┌──────────┐    ┌─────────────┐    ┌──────────────┐
   │   Your   │───▶│  Gateway    │───▶│   Payment    │
   │   App    │    │   Manager   │    │   Gateway    │
   └──────────┘    └─────────────┘    └──────────────┘
                          │                   │
                          ▼                   ▼
                   PaymentInitiated    User redirected
                   Event fired         to gateway

2. USER PAYS
   ┌──────────┐    ┌──────────────┐
   │   User   │───▶│   Gateway    │
   │          │    │   (Stripe,   │
   │          │◀───│   CHIP, etc) │
   └──────────┘    └──────────────┘

3. CALLBACK (Unified Route)
   ┌──────────────┐    ┌─────────────────────┐
   │   Gateway    │───▶│  /payment/webhook/  │
   │   Callback   │    │      {driver}       │
   └──────────────┘    └─────────────────────┘
          │                      │
          │         ┌────────────┴────────────┐
          │         │                         │
          ▼         ▼                         ▼
       [POST]    [GET for               [GET for
       Webhook   Stripe/PayPal]         CHIP/ToyyibPay]
          │           │                       │
          │           │                       │
          ▼           ▼                       ▼
       Verify     Verify via API        Just redirect
       payload    (session_id/token)    (POST already verified)
          │           │                       │
          └───────────┴───────────────────────┘
                          │
                          ▼
                   Update payment status
                   Fire events
                   Send notifications
                          │
                          ▼
               ┌─────────────────────┐
               │   Status Page       │
               │ /payment/status/REF │
               └─────────────────────┘
```

---

## Gateway Types

### Webhook-Based Gateways (CHIP, ToyyibPay)

These gateways send payment verification via **POST webhook**. The user return (GET) just redirects to the status page.

```
POST /payment/webhook/chip    → Verify payment, update status
GET  /payment/webhook/chip    → Redirect to status page
```

### API-Based Gateways (Stripe, PayPal)

These gateways require an **API call** to verify payment. Verification can happen on both POST webhook and GET return.

```
POST /payment/webhook/stripe  → Verify via webhook payload
GET  /payment/webhook/stripe  → Verify via session_id API call
```

---

## Supported Gateways

| Gateway          | Type    | Return URL | Refund | Notes |
| ---------------- | ------- | ---------- | ------ | ----- |
| **CHIP**         | Webhook | ✅         | Planned, not implemented yet | Callback handling built in, signature verification still stubbed |
| **ToyyibPay**    | Webhook | ✅         | ❌ | Callback handling built in, signature verification still permissive |
| **Stripe**       | API     | ✅         | ✅ | Webhook signature verification supported when `STRIPE_WEBHOOK_SECRET` is set |
| **PayPal**       | API     | ✅         | ✅ | Return verification works, webhook signature verification still stubbed |
| **Manual Proof** | Manual  | ❌         | Manual only | Status managed inside your app |

The customer status page mainly shows the package's stored payment status. In the current implementation, gateway-side `checkStatus()` calls for CHIP, ToyyibPay, Stripe, and PayPal still return placeholder responses rather than a full live lookup.

---

## Configuration

### Publish Config

```bash
php artisan vendor:publish --tag=payment-gateway-config
```

### Key Configuration Options

```php
// config/payment-gateway.php

return [
    // Default gateway
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'chip'),

    // Your Payable model (use built-in or your own)
    'model' => \Ejoi8\MalaysiaPaymentGateway\Models\Payment::class,

    // Route configuration
    'routes' => [
        'prefix' => 'payment',
        'middleware' => ['web'],
    ],

    // Shared package settings
    'settings' => [
        'default_currency' => env('PAYMENT_DEFAULT_CURRENCY', 'MYR'),
        'max_items' => env('PAYMENT_MAX_ITEMS', 10),
    ],

    // Status portal (customer tracking)
    'status_portal' => [
        'enabled' => true,
        'path' => 'check-status',
    ],

    // Email notifications
    'notifications' => [
        'enabled' => env('PAYMENT_NOTIFICATIONS_ENABLED', true),
        'queue' => env('PAYMENT_NOTIFICATIONS_QUEUE', false),
        'email_success' => true,
        'email_failure' => true,
        'email_initiated' => true,
    ],

    // Developer sandbox
    'sandbox' => [
        'enabled' => env('PAYMENT_GATEWAY_SANDBOX', false),
        'prefix' => 'payment-gateway',
        'middleware' => ['web'],
    ],
];
```

`routes.middleware` currently applies to the status pages. The webhook route is registered separately at `/payment/webhook/{driver}` with `api` middleware in the current implementation.

---

## URLs Reference

| URL                           | Method   | Purpose                                   |
| ----------------------------- | -------- | ----------------------------------------- |
| `/payment/webhook/{driver}`   | GET/POST | Unified callback for webhooks and returns |
| `/payment/status/{reference}` | GET      | Payment status page for customer          |
| `/payment/check-status`       | GET      | Status portal (search by reference)       |
| `/payment/check-status/search`| GET      | Status portal search endpoint             |
| `/payment-gateway/sandbox`    | GET      | Developer sandbox (when enabled)          |

---

## Using Your Own Model

If you want to use your own `Booking` or `Order` model instead of the built-in `Payment` model:

### 1. Implement `PayableInterface`

```php
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

class Booking extends Model implements PayableInterface
{
    public function getPaymentReference(): string
    {
        return $this->reference_number;
    }

    public function getPaymentAmount(): int
    {
        return $this->total_amount; // in cents
    }

    public function getPaymentCurrency(): string
    {
        return 'MYR';
    }

    public function getPaymentCustomer(): array
    {
        return [
            'name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone,
        ];
    }

    public function getPaymentItems(): array
    {
        return $this->items->map(fn($item) => [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'price' => $item->price,
        ])->toArray();
    }

    public function getPaymentDescription(): string
    {
        return "Booking #{$this->reference_number}";
    }

    public function getPaymentSettings(): array
    {
        return config('payment-gateway.settings', []);
    }

    public function getPaymentUrls(): array
    {
        $driver = $this->gateway ?? config('payment-gateway.default', 'chip');
        $webhookUrl = route('payment-gateway.webhook', ['driver' => $driver]);

        return [
            'return_url' => $webhookUrl,
            'callback_url' => $webhookUrl,
            'cancel_url' => route('payment-gateway.status.portal'),
        ];
    }

    public static function findByReference(string $reference): ?self
    {
        return static::where('reference_number', $reference)->first();
    }
}
```

### 2. Recommended Columns / Update Hook

The package works best when your model has these fields:

- `status`
- `gateway`
- `transaction_id`
- `items`
- `metadata`

If your model uses different field names, add a small mapper so package events can still update it automatically:

```php
public function applyPaymentGatewayUpdate(array $attributes): void
{
    if (isset($attributes['status'])) {
        $this->payment_state = $attributes['status'];
    }

    if (isset($attributes['transaction_id'])) {
        $this->gateway_transaction_ref = $attributes['transaction_id'];
    }

    if (isset($attributes['metadata'])) {
        $this->payment_meta = $attributes['metadata'];
    }

    $this->save();
}
```

### 3. Update Config

```php
// config/payment-gateway.php
'model' => \App\Models\Booking::class,
```

### 4. Sandbox Support For Custom Models

The developer sandbox now honors `payment-gateway.model`.

If your custom model uses the package-style columns (`reference`, `amount`, `currency`, `description`, etc.), the sandbox can create records automatically.

If your model uses different field names, add a static factory:

```php
public static function createForSandbox(array $attributes): static
{
    return static::create([
        'reference_number' => $attributes['reference'],
        'total_amount' => $attributes['amount'],
        'currency_code' => $attributes['currency'],
        'description_text' => $attributes['description'],
        'payment_gateway' => $attributes['gateway'],
        'payment_state' => $attributes['status'],
    ]);
}
```

---

## Events

Listen for these events to add custom logic:

```php
// In EventServiceProvider or listener

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;

Event::listen(PaymentSucceeded::class, function (PaymentSucceeded $event) {
    Log::info('Payment successful', [
        'reference' => $event->payable->getPaymentReference(),
        'gateway' => $event->gateway,
        'transaction_id' => $event->transactionId,
    ]);
});
```

### Option 2: Using an Event Subscriber (Recommended)

Keep your `AppServiceProvider` clean by grouping listeners together.

**1. Create the Listener:**

```php
namespace App\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;

class UpdateOrderPaymentStatus
{
    public function handleSuccess(PaymentSucceeded $event) {
        // $event->payable, $event->gateway, $event->transactionId
    }

    public function handleFailure(PaymentFailed $event) {
        // $event->payable, $event->error
    }

    public function subscribe($events)
    {
        return [
            PaymentSucceeded::class => 'handleSuccess',
            PaymentFailed::class => 'handleFailure',
        ];
    }
}
```

**2. Register in `AppServiceProvider`:**

```php
public function boot(): void
{
    Event::subscribe(UpdateOrderPaymentStatus::class);
}
```

### Available Events

| Event              | When Fired                                      |
| ------------------ | ----------------------------------------------- |
| `PaymentInitiated` | After payment is created and user is redirected |
| `PaymentSucceeded` | After payment is verified as successful         |
| `PaymentFailed`    | After payment is verified as failed             |
| `PaymentRefunded`  | After a refund is processed                     |

---

## Developer Sandbox

Test gateways without writing code.

### Enable

```env
PAYMENT_GATEWAY_SANDBOX=true
```

### Access

Visit `/payment-gateway/sandbox` in your browser by default. Both the prefix and middleware are configurable under `payment-gateway.sandbox`.

### Features

- Test all configured gateways
- Respects gateway `enabled` flags
- Override credentials on-the-fly
- Multiple payment scenarios:
  simple, invoice, booking, e-commerce, event, subscription
- Uses the configured `payment-gateway.model`
- View raw API responses

If your configured payable model does not use the package-style columns, add `createForSandbox(array $attributes)` as shown above.

⚠️ **Never enable in production!**

---

## Enums (Type-Safe Status & Gateway Types)

The package uses PHP 8.1 Enums for type-safe status values and gateway classifications.

### PaymentStatus Enum

Centralized payment status values - no more hardcoding strings!

```php
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;

// Check status types
if (PaymentStatus::isSuccess($payment->status)) {
    // Handle successful payment
}

if (PaymentStatus::isPending($payment->status)) {
    // Payment is still pending
}

if (PaymentStatus::isFailed($payment->status)) {
    // Payment failed
}

// Get all statuses of a type
$successStatuses = PaymentStatus::successStatuses();
// Returns: ['paid', 'successful', 'success', 'completed']

$pendingStatuses = PaymentStatus::pendingStatuses();
// Returns: ['pending', 'created', 'pending_verification']

// Get human-readable message
$message = PaymentStatus::getMessage('paid');
// Returns: 'Payment has been successfully received. Thank you!'

// Get CSS class for styling
$cssClass = PaymentStatus::getCssClass('paid');
// Returns: 'status-paid'

// Get default values for saving
$payment->status = PaymentStatus::defaultSuccessStatus();  // 'paid'
$payment->status = PaymentStatus::defaultFailedStatus();   // 'failed'
$payment->status = PaymentStatus::defaultPendingStatus();  // 'pending'
```

#### Available Statuses

| Enum Value   | String         | Category |
| ------------ | -------------- | -------- |
| `PAID`       | `'paid'`       | Success  |
| `SUCCESSFUL` | `'successful'` | Success  |
| `SUCCESS`    | `'success'`    | Success  |
| `COMPLETED`  | `'completed'`  | Success  |
| `PENDING`    | `'pending'`    | Pending  |
| `CREATED`    | `'created'`    | Pending  |
| `PENDING_VERIFICATION` | `'pending_verification'` | Pending |
| `FAILED`     | `'failed'`     | Failed   |
| `CANCELLED`  | `'cancelled'`  | Failed   |
| `EXPIRED`    | `'expired'`    | Failed   |
| `REFUNDED`   | `'refunded'`   | Other    |
| `UNKNOWN`    | `'unknown'`    | Other    |

---

### GatewayType Enum

Each gateway self-declares its verification type.

```php
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;

// Get gateway type
$gateway = $manager->driver('chip');
$type = $gateway->getType();  // GatewayType::WEBHOOK

// Check verification behavior
if ($type->requiresGetVerification()) {
    // For Stripe/PayPal - must verify on GET return via API
}

if ($type->usesWebhook()) {
    // For CHIP/ToyyibPay - webhook handles verification
}
```

#### Gateway Types

| Type      | Gateways        | Behavior                                                                          |
| --------- | --------------- | --------------------------------------------------------------------------------- |
| `WEBHOOK` | CHIP, ToyyibPay | Verification via POST webhook. GET return just redirects.                         |
| `API`     | Stripe, PayPal  | Verification via API call. GET return contains session_id/token for verification. |
| `MANUAL`  | Manual Proof    | No automated verification. Requires manual approval.                              |

---

### Adding a New Gateway

When implementing a new gateway, declare its type:

```php
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;

class MyCustomGateway implements GatewayInterface
{
    public function getType(): GatewayType
    {
        return GatewayType::WEBHOOK; // or API, MANUAL
    }

    // ... other methods
}
```

---

## Webhook Setup

### CHIP

In your CHIP dashboard, set the callback URL to:

```
https://your-domain.com/payment/webhook/chip
```

Current caveat: callback extraction works, but signature verification is still stubbed in the package.

### ToyyibPay

When creating bills, the package automatically sets:

- `billCallbackUrl` → `/payment/webhook/toyyibpay`
- `billReturnUrl` → `/payment/webhook/toyyibpay`

ToyyibPay callbacks/returns are identified from payload fields such as `order_id`, `billcode`, or `refno`.

Current caveat: ToyyibPay callback verification is still permissive because the package does not yet implement stronger authenticity checks.

### Stripe

Stripe works without configuring webhooks (uses return URL verification).

Optionally, for more reliable verification, configure in Stripe Dashboard:

```
Webhook URL: https://your-domain.com/payment/webhook/stripe
Events: checkout.session.completed, payment_intent.succeeded
```

The package also supports GET return verification using the `session_id` Stripe appends to the success URL.

For webhook signature verification, set `STRIPE_WEBHOOK_SECRET`.

### PayPal

PayPal works without configuring webhooks (uses return URL verification).

Optionally, configure in PayPal Developer Dashboard:

```
Webhook URL: https://your-domain.com/payment/webhook/paypal
Events: PAYMENT.CAPTURE.COMPLETED
```

The package also supports GET return verification using PayPal's `token` / `orderID` query parameters.

Current caveat: PayPal webhook signature verification is still stubbed. The GET return flow is currently the more complete path.

---

## Testing

Run the package tests:

```bash
composer install
vendor/bin/phpunit
```

---

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x

---

## License

MIT
