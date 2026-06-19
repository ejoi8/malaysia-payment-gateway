<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Listeners\Concerns\PersistsPayableState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UpdatePaymentStatus
{
    use PersistsPayableState;

    /**
     * Handle the event.
     *
     * @param  object  $event
     */
    public function handle($event): void
    {
        if ($event instanceof PaymentRefunded) {
            $this->handleRefund($event);

            return;
        }

        $payable = $event->payable;

        if ($event instanceof PaymentSucceeded) {
            $this->persistPayableState($payable, [
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

            $this->persistPayableState($payable, [
                'status' => PaymentStatus::defaultFailedStatus(),
                'metadata' => $metadata,
            ]);

            Log::info("Payment [{$payable->getPaymentReference()}] marked as FAILED: ".$event->error);
        }
    }

    /**
     * Mark the refunded payment as refunded.
     *
     * The refund event only carries the transaction id (refunds are initiated by
     * id, not by payable), so we resolve the payable via the optional
     * findByTransactionId() hook on the configured model. Models that don't
     * expose it simply won't get automatic refund-status persistence.
     */
    protected function handleRefund(PaymentRefunded $event): void
    {
        $modelClass = config('payment-gateway.model');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! method_exists($modelClass, 'findByTransactionId')) {
            return;
        }

        // Best-effort: a failed lookup/persist must not break the refund flow.
        try {
            $payable = $modelClass::findByTransactionId($event->transactionId);

            if (! $payable instanceof PayableInterface) {
                Log::warning("Refund: no payable found for transaction {$event->transactionId}");

                return;
            }

            $metadata = [];

            if ($payable instanceof Model) {
                $metadata = (array) ($payable->getAttribute('metadata') ?? []);
            }

            $metadata['refund'] = [
                'amount' => $event->amount,
                'meta' => $event->meta,
                'refunded_at' => now()->toDateTimeString(),
            ];

            $this->persistPayableState($payable, [
                'status' => PaymentStatus::REFUNDED->value,
                'metadata' => $metadata,
            ]);

            Log::info("Payment for transaction [{$event->transactionId}] marked as REFUNDED.");
        } catch (\Throwable $e) {
            Log::error('Refund status update failed.', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
