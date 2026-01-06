<?php

namespace Ejoi8\MalaysiaPaymentGateway\Events;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

/**
 * Fired when a payment is successfully verified.
 */
class PaymentSucceeded
{
    public function __construct(
        public PayableInterface $payable,
        public string $gateway,
        public string $transactionId,
        public array $meta = []
    ) {}
}
