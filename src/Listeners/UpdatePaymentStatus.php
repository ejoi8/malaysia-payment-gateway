<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Models\Payment;
use Illuminate\Support\Facades\Log;

class UpdatePaymentStatus
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $payable = $event->payable;

        // Only update if it is the package's default Payment model
        if (! $payable instanceof Payment) {
            return;
        }

        if ($event instanceof PaymentSucceeded) {
            $payable->status = PaymentStatus::defaultSuccessStatus();
            $payable->transaction_id = $event->transactionId;
            $payable->save();

            Log::info("Payment [{$payable->reference}] marked as PAID.");
        }

        if ($event instanceof PaymentFailed) {
            $payable->status = PaymentStatus::defaultFailedStatus();
            // Store the error message in metadata
            $meta = $payable->metadata ?? [];
            $meta['failure_reason'] = $event->error;
            $payable->metadata = $meta;
            $payable->save();

            Log::info("Payment [{$payable->reference}] marked as FAILED: ".$event->error);
        }
    }
}

