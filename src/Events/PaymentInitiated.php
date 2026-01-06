<?php

namespace Ejoi8\MalaysiaPaymentGateway\Events;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

/**
 * Fired when a payment is initiated.
 */
class PaymentInitiated
{
    public function __construct(
        public PayableInterface $payable,
        public string $gateway,
        public array $response
    ) {}
}
