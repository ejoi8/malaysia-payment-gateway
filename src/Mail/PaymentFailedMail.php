<?php

namespace Ejoi8\MalaysiaPaymentGateway\Mail;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PayableInterface $payable)
    {}

    public function build()
    {
        return $this->subject('Payment Failed: ' . $this->payable->getPaymentReference())
                    ->view('payment-gateway::mail.payment_failed');
    }
}
