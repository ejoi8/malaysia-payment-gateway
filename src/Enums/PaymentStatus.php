<?php

namespace Ejoi8\MalaysiaPaymentGateway\Enums;

/**
 * Payment Status Enum
 *
 * Centralizes all payment status values to avoid hardcoding.
 * Use this enum throughout the package for consistent status handling.
 */
enum PaymentStatus: string
{
    // Success statuses
    case PAID = 'paid';
    case SUCCESSFUL = 'successful';
    case SUCCESS = 'success';
    case COMPLETED = 'completed';

    // Pending statuses
    case PENDING = 'pending';
    case CREATED = 'created';

    // Failed statuses
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    // Other statuses
    case REFUNDED = 'refunded';
    case UNKNOWN = 'unknown';

    /**
     * All statuses that indicate successful payment.
     */
    public static function successStatuses(): array
    {
        return [
            self::PAID->value,
            self::SUCCESSFUL->value,
            self::SUCCESS->value,
            self::COMPLETED->value,
        ];
    }

    /**
     * All statuses that indicate pending payment.
     */
    public static function pendingStatuses(): array
    {
        return [
            self::PENDING->value,
            self::CREATED->value,
        ];
    }

    /**
     * All statuses that indicate failed payment.
     */
    public static function failedStatuses(): array
    {
        return [
            self::FAILED->value,
            self::CANCELLED->value,
            self::EXPIRED->value,
        ];
    }

    /**
     * Check if a status string indicates success.
     */
    public static function isSuccess(string $status): bool
    {
        return in_array($status, self::successStatuses());
    }

    /**
     * Check if a status string indicates pending.
     */
    public static function isPending(string $status): bool
    {
        return in_array($status, self::pendingStatuses());
    }

    /**
     * Check if a status string indicates failure.
     */
    public static function isFailed(string $status): bool
    {
        return in_array($status, self::failedStatuses());
    }

    /**
     * Get human-readable message for a status.
     */
    public static function getMessage(string $status): string
    {
        return match ($status) {
            self::PAID->value,
            self::SUCCESSFUL->value,
            self::SUCCESS->value,
            self::COMPLETED->value => 'Payment has been successfully received. Thank you!',

            self::PENDING->value,
            self::CREATED->value => 'Payment is pending. Waiting for confirmation.',

            self::FAILED->value => 'Payment was not successful. Please try again.',
            self::CANCELLED->value => 'Payment was cancelled.',
            self::EXPIRED->value => 'Payment has expired.',
            self::REFUNDED->value => 'Payment has been refunded.',

            default => 'Status: ' . ucfirst($status),
        };
    }

    /**
     * Get CSS class for status badge styling.
     */
    public static function getCssClass(string $status): string
    {
        if (self::isSuccess($status)) {
            return 'status-paid';
        }

        if (self::isFailed($status)) {
            return 'status-failed';
        }

        return 'status-pending';
    }

    /**
     * The default status to use when setting payment as paid.
     * This is what gets saved to the database on successful payment.
     */
    public static function defaultSuccessStatus(): string
    {
        return self::PAID->value;
    }

    /**
     * The default status for failed payments.
     */
    public static function defaultFailedStatus(): string
    {
        return self::FAILED->value;
    }

    /**
     * The default status for new payments.
     */
    public static function defaultPendingStatus(): string
    {
        return self::PENDING->value;
    }
}
