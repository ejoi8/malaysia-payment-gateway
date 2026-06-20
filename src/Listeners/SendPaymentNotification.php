<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentInitiated;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentAdminMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentFailedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentInitiatedMail;
use Ejoi8\MalaysiaPaymentGateway\Mail\PaymentSucceededMail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send payment notification emails.
 *
 * Sends the customer-facing receipt emails and, when an admin recipient is
 * configured, a merchant-facing alert on success/failure. Supports both
 * synchronous and queued sending, and swallows mail failures so they never
 * break the payment flow.
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
        $reference = $payable->getPaymentReference();
        $useQueue = $config['queue'] ?? false;

        $this->notifyCustomer($event, $config, $useQueue, $reference);
        $this->notifyAdmin($event, $config, $useQueue, $reference);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function notifyCustomer(object $event, array $config, bool $useQueue, string $reference): void
    {
        $email = $event->payable->getPaymentCustomer()['email'] ?? null;

        if (! $email) {
            Log::warning('Payment customer notification skipped: missing customer email.', [
                'event' => class_basename($event),
                'reference' => $reference,
            ]);

            return;
        }

        $payable = $event->payable;

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
     * @param  array<string, mixed>  $config
     */
    protected function notifyAdmin(object $event, array $config, bool $useQueue, string $reference): void
    {
        $recipients = $this->recipients($config['admin_email'] ?? null);

        if ($recipients === []) {
            return;
        }

        $payable = $event->payable;

        if ($event instanceof PaymentSucceeded) {
            $this->sendMail($recipients, new PaymentAdminMail($payable, 'paid', $event->gateway), 'admin_success', $useQueue, $reference);
        }

        if ($event instanceof PaymentFailed) {
            $this->sendMail($recipients, new PaymentAdminMail($payable, 'failed', $event->gateway, $event->error), 'admin_failure', $useQueue, $reference);
        }
    }

    /**
     * Normalise the admin recipient config into a list of addresses.
     *
     * @return array<int, string>
     */
    protected function recipients(mixed $admin): array
    {
        if (is_array($admin)) {
            return array_values(array_filter($admin));
        }

        if (! is_string($admin) || trim($admin) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $admin))));
    }

    /**
     * Send email with error handling so failures don't break the payment flow.
     *
     * @param  string|array<int, string>  $to
     */
    protected function sendMail(string|array $to, Mailable $mailable, string $type, bool $useQueue, string $reference): void
    {
        try {
            if ($useQueue) {
                Mail::to($to)->queue($mailable);
            } else {
                Mail::to($to)->send($mailable);
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
