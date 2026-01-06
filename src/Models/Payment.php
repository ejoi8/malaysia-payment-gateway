<?php

namespace Ejoi8\MalaysiaPaymentGateway\Models;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model implements PayableInterface
{
    use SoftDeletes;

    protected $table = 'mpg_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'items' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get unique payment reference.
     */
    public function getPaymentReference(): string
    {
        return $this->reference;
    }

    /**
     * Get total amount in cents.
     */
    public function getPaymentAmount(): int
    {
        return $this->amount;
    }

    /**
     * Get currency code.
     */
    public function getPaymentCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Get customer details.
     */
    public function getPaymentCustomer(): array
    {
        return [
            'name' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->customer_phone,
        ];
    }

    /**
     * Get gateway settings.
     */
    public function getPaymentSettings(): array
    {
        // Check metadata for specific overrides, or fallback to config
        return $this->metadata['settings'] ?? config('payment-gateway.settings', []);
    }

    /**
     * Get callback URLs.
     *
     * These URLs are used by payment gateways for:
     * - return_url: Where user is redirected after payment (GET)
     * - callback_url: Where gateway sends webhooks (POST)
     * - cancel_url: Where user is redirected if they cancel
     * - success_url: Optional custom success page
     * - failure_url: Optional custom failure page
     */
    public function getPaymentUrls(): array
    {
        // Allow overrides from metadata if provided
        if (! empty($this->metadata['urls'])) {
            return $this->metadata['urls'];
        }

        // Use unified webhook route for both return URL and callback
        // This ensures both GET (user return) and POST (webhook) work
        $webhookUrl = route('payment-gateway.webhook', ['driver' => $this->gateway ?? 'chip']);

        return [
            'return_url' => $webhookUrl,   // User return (GET) - Stripe session_id, PayPal token
            'callback_url' => $webhookUrl, // Gateway webhook (POST) - CHIP, ToyyibPay, Stripe webhook
            'cancel_url' => route('payment-gateway.status.portal'),
        ];
    }

    /**
     * Get line items.
     */
    public function getPaymentItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * Get description.
     */
    public function getPaymentDescription(): string
    {
        return $this->description;
    }

    /**
     * Find by reference (Interface implementation).
     */
    public static function findByReference(string $reference): ?self
    {
        return static::where('reference', $reference)->first();
    }
}
