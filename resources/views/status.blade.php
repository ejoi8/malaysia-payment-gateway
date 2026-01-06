<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - {{ $payable->getPaymentReference() }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 28rem;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-paid,
        .status-successful {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-pending,
        .status-created {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .details {
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .row:last-child {
            border-bottom: none;
        }

        .label {
            color: #6b7280;
        }

        .value {
            font-weight: 500;
            text-align: right;
        }

        .actions {
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #4f46e5;
            color: white;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #4338ca;
        }

        .btn-secondary {
            background-color: #9ca3af;
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background-color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <h1>Payment Status</h1>
            @php
                use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
                $status = $check['status'] ?? PaymentStatus::UNKNOWN->value;
                $statusClass = PaymentStatus::getCssClass($status);
            @endphp
            <span class="status-badge {{ $statusClass }}">{{ ucfirst($status) }}</span>
        </div>

        <div class="details">
            <div class="row">
                <span class="label">Reference</span>
                <span class="value">{{ $payable->getPaymentReference() }}</span>
            </div>
            <div class="row">
                <span class="label">Amount</span>
                <span class="value">{{ number_format($payable->getPaymentAmount() / 100, 2) }}
                    {{ $payable->getPaymentCurrency() }}</span>
            </div>
            <div class="row">
                <span class="label">Customer</span>
                <span class="value">{{ $payable->getPaymentCustomer()['name'] ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Description</span>
                <span class="value">{{ $payable->getPaymentDescription() }}</span>
            </div>
            <div class="row">
                <span class="label">Message</span>
                <span class="value">{{ $check['message'] ?? 'No additional info' }}</span>
            </div>
        </div>

        <div class="actions">
            @if (isset($check['payment_url']) && in_array($status, ['pending', 'created']))
                <a href="{{ $check['payment_url'] }}" class="btn">Pay Now</a>
            @else
                <a href="{{ url('/') }}" class="btn">Back to Home</a>
            @endif

            <a href="{{ route('payment-gateway.status.portal') }}"
                style="display:block; margin-top:1rem; color:#6b7280; font-size:0.875rem; text-decoration:none;">Check
                Another</a>
        </div>
    </div>
</body>

</html>
