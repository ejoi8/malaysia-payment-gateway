<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Database\Eloquent\Model;

class TestEloquentPayable extends Model implements PayableInterface
{
    protected $table = 'test_payables';

    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'metadata' => 'array',
    ];

    public bool $wasSaved = false;

    public function getPaymentReference(): string
    {
        return (string) $this->getAttribute('reference');
    }

    public function getPaymentAmount(): int
    {
        return (int) $this->getAttribute('amount');
    }

    public function getPaymentCurrency(): string
    {
        return (string) $this->getAttribute('currency');
    }

    public function getPaymentCustomer(): array
    {
        return $this->getAttribute('customer') ?? [];
    }

    public function getPaymentSettings(): array
    {
        return [];
    }

    public function getPaymentUrls(): array
    {
        return [];
    }

    public function getPaymentItems(): array
    {
        return $this->getAttribute('items') ?? [];
    }

    public function getPaymentDescription(): string
    {
        return (string) $this->getAttribute('description');
    }

    public static function findByReference(string $reference): ?self
    {
        return null;
    }

    public function save(array $options = []): bool
    {
        $this->wasSaved = true;

        return true;
    }
}
