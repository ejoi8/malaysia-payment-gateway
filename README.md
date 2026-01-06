# Malaysia Payment Gateway

A Laravel package for payment gateway integrations. Supports Malaysian gateways (CHIP, ToyyibPay) plus international gateways (Stripe, PayPal).

## Features

✅ **Multiple Gateways** - CHIP, ToyyibPay, Stripe, PayPal, Manual Proof  
✅ **Unified API** - Same interface for all gateways  
✅ **Automatic Webhooks** - Built-in webhook handling  
✅ **Return URL Handling** - Unified callback for both webhooks and user returns  
✅ **Status Portal** - Customer-facing payment tracking  
✅ **Email Notifications** - Automatic payment receipts  
✅ **Developer Sandbox** - Test gateways without writing code

---

## Quick Start

### 1. Installation

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/malaysia-payment-gateway"
        }
    ],
    "require": {
        "ejoi8/malaysia-payment-gateway": "*"
    }
}
```

```bash
composer update ejoi8/malaysia-payment-gateway
php artisan migrate
```

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

# PayPal
PAYPAL_CLIENT_ID=your-client-id
PAYPAL_CLIENT_SECRET=your-client-secret
PAYPAL_SANDBOX=true

# Currency Configuration (optional - defaults to MYR)
PAYMENT_DEFAULT_CURRENCY=MYR
CHIP_CURRENCY=MYR
STRIPE_CURRENCY=MYR
PAYPAL_CURRENCY=MYR
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

| Gateway          | Type    | Webhook | Return URL | Refund |
| ---------------- | ------- | ------- | ---------- | ------ |
| **CHIP**         | Webhook | ✅      | ✅         | 🔜     |
| **ToyyibPay**    | Webhook | ✅      | ✅         | ❌     |
| **Stripe**       | API     | ✅      | ✅         | ✅     |
| **PayPal**       | API     | ✅      | ✅         | ✅     |
| **Manual Proof** | N/A     | ❌      | ❌         | ❌     |

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

    // Status portal (customer tracking)
    'status_portal' => [
        'enabled' => true,
        'path' => 'check-status',
    ],

    // Email notifications
    'notifications' => [
        'enabled' => true,
        'email_on_initiate' => true,
        'email_on_success' => true,
        'email_on_failure' => true,
    ],

    // Developer sandbox
    'sandbox' => [
        'enabled' => env('PAYMENT_GATEWAY_SANDBOX', false),
    ],
];
```

---

## URLs Reference

| URL                           | Method   | Purpose                                   |
| ----------------------------- | -------- | ----------------------------------------- |
| `/payment/webhook/{driver}`   | GET/POST | Unified callback for webhooks and returns |
| `/payment/status/{reference}` | GET      | Payment status page for customer          |
| `/payment/check-status`       | GET      | Status portal (search by reference)       |
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
        $webhookUrl = route('payment-gateway.webhook', ['driver' => $this->gateway]);

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

### 2. Update Config

```php
// config/payment-gateway.php
'model' => \App\Models\Booking::class,
```

---

## Events

Listen for these events to add custom logic:

```php
// In EventServiceProvider or listener

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;

// Example: Update booking status after payment succeeds
Event::listen(PaymentSucceeded::class, function ($event) {
    $payment = $event->payable;

    // Your custom logic
    Log::info("Payment successful: {$payment->getPaymentReference()}");

    // Maybe update related booking
    if ($booking = Booking::find($payment->metadata['booking_id'])) {
        $booking->update(['status' => 'confirmed']);
    }
});
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

Visit `/payment-gateway/sandbox` in your browser.

### Features

- Test all configured gateways
- Override credentials on-the-fly
- Multiple payment scenarios (simple, invoice, booking, e-commerce)
- View raw API responses

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
// Returns: ['pending', 'created']

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

### ToyyibPay

When creating bills, the package automatically sets:

- `billCallbackUrl` → `/payment/webhook/toyyibpay`
- `billReturnUrl` → `/payment/webhook/toyyibpay?reference=XXX`

### Stripe

Stripe works without configuring webhooks (uses return URL verification).

Optionally, for more reliable verification, configure in Stripe Dashboard:

```
Webhook URL: https://your-domain.com/payment/webhook/stripe
Events: checkout.session.completed, payment_intent.succeeded
```

### PayPal

PayPal works without configuring webhooks (uses return URL verification).

Optionally, configure in PayPal Developer Dashboard:

```
Webhook URL: https://your-domain.com/payment/webhook/paypal
Events: PAYMENT.CAPTURE.COMPLETED
```

---

## Testing

Run the package tests:

```bash
cd packages/malaysia-payment-gateway
./vendor/bin/phpunit
```

---

## Requirements

- PHP 8.2+
- Laravel 10.x or 11.x

---

## License

MIT
