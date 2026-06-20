<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Listeners\Concerns\PersistsPayableState;
use Illuminate\Database\Eloquent\Model;

/**
 * Persist the gateway's transaction id at initiation time.
 *
 * Gateways return an id from initiate() (CHIP purchase id, Stripe session id,
 * PayPal order id, ToyyibPay bill code). Storing it immediately — without
 * touching the status — lets you reconcile or poll the gateway for a payment
 * that hasn't been confirmed yet. The verified id (which may differ) overwrites
 * it later when the payment succeeds.
 *
 * Enabled by default; disable via config('payment-gateway.persist_initiation_id').
 */
class PersistInitiationId
{
    use PersistsPayableState;

    public function handle(PaymentInitiated $event): void
    {
        if (! config('payment-gateway.persist_initiation_id', true)) {
            return;
        }

        $transactionId = $event->response['transaction_id'] ?? null;

        if (! $transactionId) {
            return;
        }

        $payable = $event->payable;

        // Don't overwrite an id that's already been recorded.
        $current = $payable instanceof Model
            ? $payable->getAttribute('transaction_id')
            : ($payable->transaction_id ?? null);

        if (! empty($current)) {
            return;
        }

        $this->persistPayableState($payable, ['transaction_id' => $transactionId]);
    }
}
