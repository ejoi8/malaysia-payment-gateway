<?php

namespace Ejoi8\MalaysiaPaymentGateway\Events;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;

/**
 * Fired when a payment fails.
 */
class PaymentFailed
{
    public function __construct(
        public PayableInterface $payable,
        public string $gateway,
        public string $error,
        public array $meta = []
    ) {}
}
