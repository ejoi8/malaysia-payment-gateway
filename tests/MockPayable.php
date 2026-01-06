<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

/**
 * Mock payable entity for testing.
 */
class MockPayable implements PayableInterface
{
    public function __construct(
        public string $reference = 'test-order-123',
        public int $amount = 5000,
        public string $currency = 'MYR',
        public array $customer = [],
        public array $settings = [],
        public array $urls = [],
        public array $items = [],
        public string $description = 'Test Order'
    ) {
        $this->customer = $customer ?: [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '0123456789',
        ];
        
        $this->settings = $settings ?: [
            'payment_item_max' => 10,
            'chip_brand_id' => 'test-brand',
        ];
        
        $this->urls = $urls ?: [
            'return_url' => 'https://example.com/return',
            'cancel_url' => 'https://example.com/cancel',
            'callback_url' => 'https://example.com/callback',
        ];
        
        $this->items = $items ?: [
            ['name' => 'Product A', 'quantity' => 1, 'price' => 2500],
            ['name' => 'Product B', 'quantity' => 1, 'price' => 2500],
        ];
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
        return $this->settings;
    }

    public function getPaymentUrls(): array
    {
        return $this->urls;
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
        if ($reference === 'test-ref' || $reference === 'test-order-123') {
            return new self(reference: $reference);
        }
        return null;
    }
}
