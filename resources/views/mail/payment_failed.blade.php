<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: sans-serif;
        }

        .container {
            padding: 20px;
        }

        .header {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Payment Failed</h2>
        </div>
        <p>Hi {{ $payable->getPaymentCustomer()['name'] ?? 'Customer' }},</p>
        <p>Unfortunately, your payment for <strong>{{ $payable->getPaymentDescription() }}</strong> could not be
            processed.</p>

        <p>Reference: {{ $payable->getPaymentReference() }}</p>

        <p>
            <a href="{{ route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]) }}"
                style="display: inline-block; padding: 10px 20px; background-color: #6366f1; color: #ffffff; text-decoration: none; border-radius: 5px;">View
                Payment Details</a>
        </p>

        <p>Please try again or contact support if the issue persists.</p>
    </div>
</body>

</html>
