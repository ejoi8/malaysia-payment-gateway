<?php

namespace Ejoi8\MalaysiaPaymentGateway\Mail;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Merchant/admin-facing notification for a payment outcome.
 *
 * Sent to config('payment-gateway.notifications.admin_email') on success and
 * failure, separately from the customer-facing receipt emails.
 */
class PaymentAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayableInterface $payable,
        public string $status,
        public string $gateway,
        public ?string $reason = null,
    ) {}

    public function build()
    {
        $label = $this->status === 'paid' ? 'Payment Received' : 'Payment Failed';

        return $this->subject('['.$label.'] '.$this->payable->getPaymentReference())
            ->view('payment-gateway::mail.payment_admin');
    }
}
