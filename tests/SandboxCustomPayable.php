<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

class SandboxCustomPayable implements PayableInterface
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $description,
        public array $customer,
        public array $items,
        public string $gateway,
        public string $status,
        public array $metadata = []
    ) {}

    public static function createForSandbox(array $attributes): self
    {
        return new self(
            reference: $attributes['reference'],
            amount: $attributes['amount'],
            currency: $attributes['currency'],
            description: $attributes['description'],
            customer: [
                'name' => $attributes['customer_name'] ?? '',
                'email' => $attributes['customer_email'] ?? '',
                'phone' => $attributes['customer_phone'] ?? '',
            ],
            items: $attributes['items'] ?? [],
            gateway: $attributes['gateway'] ?? 'chip',
            status: $attributes['status'] ?? 'pending',
            metadata: $attributes['metadata'] ?? []
        );
    }

    public function getPaymentReference(): string
    {
        return $this->reference;
    }

    public function getPaymentAmount(): int
    {
        return $this->amount;
    }

    public function getPaymentCurrency(): string
    {
        return $this->currency;
    }

    public function getPaymentCustomer(): array
    {
        return $this->customer;
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

    public function getPaymentItems(): array
    {
        return $this->items;
    }

    public function getPaymentDescription(): string
    {
        return $this->description;
    }

    public static function findByReference(string $reference): ?self
    {
        return null;
    }
}
