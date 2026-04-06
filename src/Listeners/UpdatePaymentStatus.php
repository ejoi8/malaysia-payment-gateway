<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Illuminate\Database\Eloquent\Model;
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

        if ($event instanceof PaymentSucceeded) {
            $this->persistState($payable, [
                'status' => PaymentStatus::defaultSuccessStatus(),
                'transaction_id' => $event->transactionId,
            ]);

            Log::info("Payment [{$payable->getPaymentReference()}] marked as PAID.");
        }

        if ($event instanceof PaymentFailed) {
            $metadata = [];

            if ($payable instanceof Model) {
                $metadata = (array) ($payable->getAttribute('metadata') ?? []);
            }

            $metadata['failure_reason'] = $event->error;

            $this->persistState($payable, [
                'status' => PaymentStatus::defaultFailedStatus(),
                'metadata' => $metadata,
            ]);

            Log::info("Payment [{$payable->getPaymentReference()}] marked as FAILED: ".$event->error);
        }
    }

    protected function persistState(object $payable, array $attributes): void
    {
        if (method_exists($payable, 'applyPaymentGatewayUpdate')) {
            $payable->applyPaymentGatewayUpdate($attributes);

            return;
        }

        if (! $payable instanceof Model) {
            return;
        }

        $payable->forceFill($attributes);
        $payable->save();
    }
}

