<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentFailedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentInitiatedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentSucceededMail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send payment notification emails.
 *
 * This listener handles email notifications for payment events.
 * It supports both synchronous and queued email sending, and includes
 * error handling to ensure email failures don't break the payment flow.
 */
class SendPaymentNotification
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     */
    public function handle($event): void
    {
        $config = config('payment-gateway.notifications');

        if (empty($config['enabled'])) {
            return;
        }

        $payable = $event->payable;
        $customer = $payable->getPaymentCustomer();
        $email = $customer['email'] ?? null;
        $reference = $payable->getPaymentReference();

        if (! $email) {
            Log::warning('Payment notification skipped: missing customer email.', [
                'event' => class_basename($event),
                'reference' => $reference,
            ]);

            return;
        }

        $useQueue = $config['queue'] ?? false;

        if ($event instanceof PaymentSucceeded && ! empty($config['email_success'])) {
            $this->sendMail($email, new PaymentSucceededMail($payable), 'success', $useQueue, $reference);
        }

        if ($event instanceof PaymentFailed && ! empty($config['email_failure'])) {
            $this->sendMail($email, new PaymentFailedMail($payable), 'failure', $useQueue, $reference);
        }

        if ($event instanceof PaymentInitiated && ! empty($config['email_initiated'])) {
            $this->sendMail($email, new PaymentInitiatedMail($payable), 'initiated', $useQueue, $reference);
        }
    }

    /**
     * Send email with error handling.
     *
     * This method wraps the email sending in a try-catch to ensure
     * that email failures don't break the payment flow.
     *
     * @param  string  $email  Recipient email address
     * @param  \Illuminate\Contracts\Mail\Mailable  $mailable  The mailable to send
     * @param  string  $type  Type of email for logging
     * @param  bool  $useQueue  Whether to queue the email or send synchronously
     */
    protected function sendMail(string $email, Mailable $mailable, string $type, bool $useQueue, string $reference): void
    {
        try {
            if ($useQueue) {
                Mail::to($email)->queue($mailable);
            } else {
                Mail::to($email)->send($mailable);
            }
        } catch (\Throwable $e) {
            Log::error('Payment notification failed.', [
                'type' => $type,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        }
    }
}
