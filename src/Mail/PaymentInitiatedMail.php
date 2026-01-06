<?php

namespace Ejoi8\MalaysiaPaymentGateway\Mail;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentInitiatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $payable;

    /**
     * Create a new message instance.
     */
    public function __construct(PayableInterface $payable)
    {
        $this->payable = $payable;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Payment Initiated - '.$this->payable->getPaymentReference())
            ->view('payment-gateway::mail.payment_initiated');
    }
}
