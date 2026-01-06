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
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
        }

        .details {
            margin-top: 20px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>Payment Successful</h2>
        </div>
        <p>Hi {{ $payable->getPaymentCustomer()['name'] ?? 'Customer' }},</p>
        <p>We have received your payment for <strong>{{ $payable->getPaymentDescription() }}</strong>.</p>

        <div class="details">
            <div class="row">
                <span>Reference:</span>
                <strong>{{ $payable->getPaymentReference() }}</strong>
            </div>
            <div class="row">
                <span>Amount:</span>
                <strong>{{ number_format($payable->getPaymentAmount() / 100, 2) }}
                    {{ $payable->getPaymentCurrency() }}</strong>
            </div>
            <div class="row">
                <span>Date:</span>
                <strong>{{ now()->toDayDateTimeString() }}</strong>
            </div>
        </div>

        <p>You can view your payment details anytime:</p>
        <p>
            <a href="{{ route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]) }}"
                style="display: inline-block; padding: 10px 20px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 5px;">View
                Payment Receipt</a>
        </p>

        <p>Thank you for your business.</p>
    </div>
</body>

</html>
