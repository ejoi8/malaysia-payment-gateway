<!DOCTYPE html>
<html>

<head>
    <title>Payment Initiated</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e1e1; border-radius: 5px;">
        <h2 style="color: #2563eb;">Payment Initiated</h2>
        <p>Dear {{ $payable->getPaymentCustomer()['name'] ?? 'Customer' }},</p>

        <p>This email is to confirm that we have received your request for payment.</p>

        <div style="background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 5px 0;"><strong>Reference ID:</strong> {{ $payable->getPaymentReference() }}</p>
            <p style="margin: 5px 0;"><strong>Amount:</strong> {{ number_format($payable->getPaymentAmount() / 100, 2) }}
                {{ $payable->getPaymentCurrency() }}</p>
            <p style="margin: 5px 0;"><strong>Description:</strong> {{ $payable->getPaymentDescription() }}</p>
        </div>

        <p>Please complete your payment if you haven't already. If you have completed the payment, you will receive a
            confirmation email shortly.</p>

        <p>You can check the status of your payment here:</p>
        <p>
            <a href="{{ route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]) }}"
                style="display: inline-block; padding: 10px 20px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 3px;">Track
                My Payment</a>
        </p>

        <p>Thank you,<br>
            {{ config('app.name') }}</p>
    </div>
</body>

</html>
