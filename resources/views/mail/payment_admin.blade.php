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
        <div class="header" style="background: {{ $status === 'paid' ? '#10b981' : '#ef4444' }};">
            <h2>{{ $status === 'paid' ? 'Payment Received' : 'Payment Failed' }}</h2>
        </div>

        <p>A payment has {{ $status === 'paid' ? 'been received' : 'failed' }} via
            <strong>{{ ucfirst(str_replace('_', ' ', $gateway)) }}</strong>.
        </p>

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
                <span>Customer:</span>
                <strong>{{ $payable->getPaymentCustomer()['name'] ?? 'N/A' }}
                    ({{ $payable->getPaymentCustomer()['email'] ?? 'no email' }})</strong>
            </div>
            <div class="row">
                <span>Status:</span>
                <strong>{{ ucfirst($status) }}</strong>
            </div>
            @if ($reason)
                <div class="row">
                    <span>Reason:</span>
                    <strong>{{ $reason }}</strong>
                </div>
            @endif
        </div>

        <p>
            <a href="{{ route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]) }}"
                style="display: inline-block; padding: 10px 20px; background-color: #374151; color: #ffffff; text-decoration: none; border-radius: 5px;">View
                Payment</a>
        </p>
    </div>
</body>

</html>
