<?php

namespace Ejoi8\MalaysiaPaymentGateway\Support;

/**
 * Helpers for converting between the integer "smallest unit" amounts used
 * throughout the package (cents) and the decimal strings some gateways expect.
 */
class Money
{
    /**
     * Convert an integer amount in the smallest currency unit (e.g. cents)
     * to a fixed-point decimal string, e.g. 5500 => "55.00".
     */
    public static function toDecimal(int $amountInSmallestUnit, int $decimals = 2): string
    {
        return number_format($amountInSmallestUnit / (10 ** $decimals), $decimals, '.', '');
    }
}
