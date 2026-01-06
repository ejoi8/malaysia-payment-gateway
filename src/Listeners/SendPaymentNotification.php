<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentFailedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentInitiatedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentSucceededMail;
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
        Log::info('SendPaymentNotification: Handler started for event '.get_class($event));

        $config = config('payment-gateway.notifications');

        if (empty($config['enabled'])) {
            Log::info('SendPaymentNotification: Notifications disabled in config.');

            return;
        }

        $payable = $event->payable;
        $customer = $payable->getPaymentCustomer();
        $email = $customer['email'] ?? null;

        Log::info('SendPaymentNotification: Processing for email: '.($email ?? 'NULL'));

        if (! $email) {
            Log::warning('SendPaymentNotification: No email address found, skipping notification.');

            return;
        }

        // Determine send method: queue or sync
        $useQueue = $config['queue'] ?? false;

        if ($event instanceof PaymentSucceeded && ! empty($config['email_success'])) {
            $this->sendMail($email, new PaymentSucceededMail($payable), 'Success', $useQueue);
        }

        if ($event instanceof PaymentFailed && ! empty($config['email_failure'])) {
            $this->sendMail($email, new PaymentFailedMail($payable), 'Failure', $useQueue);
        }

        if ($event instanceof PaymentInitiated && ! empty($config['email_initiated'])) {
            $this->sendMail($email, new PaymentInitiatedMail($payable), 'Initiated', $useQueue);
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
     * @param  string  $type  Type of email for logging (Success, Failure, Initiated)
     * @param  bool  $useQueue  Whether to queue the email or send synchronously
     */
    protected function sendMail(string $email, $mailable, string $type, bool $useQueue): void
    {
        try {
            Log::info("SendPaymentNotification: Sending {$type} Email...");

            if ($useQueue) {
                // Queue for background processing (non-blocking, with automatic retries)
                Mail::to($email)->queue($mailable);
                Log::info("SendPaymentNotification: {$type} Email queued successfully.");
            } else {
                // Send immediately (blocking, but works without queue worker)
                Mail::to($email)->send($mailable);
                Log::info("SendPaymentNotification: {$type} Email Sent.");
            }
        } catch (\Throwable $e) {
            // Log the error but don't break the payment flow
            Log::error("SendPaymentNotification: Failed to send {$type} Email", [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Optionally report to error tracking service (Sentry, Bugsnag, etc.)
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        }
    }
}
