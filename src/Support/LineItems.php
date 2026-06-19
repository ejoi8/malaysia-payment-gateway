<?php

namespace Ejoi8\MalaysiaPaymentGateway\Support;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

/**
 * Builds the single line a gateway is charged.
 *
 * The package never forwards the itemised cart to a payment gateway. Instead it
 * sends one line at the payable's total `amount`, so the gateway can never
 * recompute the total (Stripe), reject a mismatched breakdown (PayPal) or hit a
 * line-item limit. The full itemised list stays in your own database.
 *
 * The line is named after the payable description, with an optional "(N items)"
 * suffix so the gateway receipt still reflects a multi-item purchase.
 */
class LineItems
{
    /**
     * Total number of units across the cart (sum of each item's quantity).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function unitCount(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $total += max(1, (int) ($item['quantity'] ?? 1));
        }

        return $total;
    }

    /**
     * The name for the single gateway line: the payable's description, plus an
     * "(N items)" suffix when the cart holds more than one unit.
     */
    public static function summaryName(PayableInterface $payable): string
    {
        $name = trim($payable->getPaymentDescription());

        if (! config('payment-gateway.gateway_line.append_count', true)) {
            return $name;
        }

        $count = self::unitCount($payable->getPaymentItems());

        if ($count <= 1) {
            return $name;
        }

        $label = (string) config('payment-gateway.gateway_line.label', 'items');

        return $name.' ('.$count.' '.$label.')';
    }
}
