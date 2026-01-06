<?php

namespace Ejoi8\MalaysiaPaymentGateway\Contracts;

/**
 * Contract for any entity that can be paid for.
 *
 * Implement this interface on your models (Booking, Order, Subscription, etc.)
 * to make them compatible with the payment gateway system.
 */
interface PayableInterface
{
    /**
     * Get unique payment reference.
     * Example: 'booking-123', 'order-456'
     */
    public function getPaymentReference(): string;

    /**
     * Get total amount in cents/smallest currency unit.
     * Example: 5500 (for RM 55.00)
     */
    public function getPaymentAmount(): int;

    /**
     * Get currency code.
     * Example: 'MYR', 'USD'
     */
    public function getPaymentCurrency(): string;

    /**
     * Get customer details for the payment.
     * Example: ['name' => 'John', 'email' => 'john@example.com', 'phone' => '012...']
     */
    public function getPaymentCustomer(): array;

    /**
     * Get payment gateway settings.
     * These are typically from your organization/tenant settings.
     */
    public function getPaymentSettings(): array;

    /**
     * Get callback URLs for payment gateway.
     * Example: ['return_url' => '...', 'callback_url' => '...']
     */
    public function getPaymentUrls(): array;

    /**
     * Get line items for the payment.
     * Example: [['name' => 'Court A (10:00-11:00)', 'quantity' => 1, 'price' => 5500]]
     */
    public function getPaymentItems(): array;

    /**
     * Get description for the payment.
     */
    public function getPaymentDescription(): string;

    /**
     * Find a payable record by its reference identifier.
     * 
     * @param string $reference
     */
    public static function findByReference(string $reference): ?self;
}
