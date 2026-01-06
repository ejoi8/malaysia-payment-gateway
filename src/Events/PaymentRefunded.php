<?php

namespace Ejoi8\MalaysiaPaymentGateway\Events;

/**
 * Fired when a refund is processed.
 */
class PaymentRefunded
{
    public function __construct(
        public string $transactionId,
        public string $gateway,
        public ?int $amount = null,
        public array $meta = []
    ) {}
}
