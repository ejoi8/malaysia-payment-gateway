<?php

namespace Ejoi8\MalaysiaPaymentGateway\Mail;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceededMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PayableInterface $payable)
    {}

    public function build()
    {
        return $this->subject('Payment Receipt: ' . $this->payable->getPaymentReference())
                    ->view('payment-gateway::mail.payment_succeeded');
    }
}
