<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Payment Status</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            border: 1px solid #e5e7eb;
        }

        h1 {
            margin: 0 0 0.5rem 0;
            color: #111827;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
        }

        p {
            color: #6b7280;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.875rem;
        }

        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.15s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid #93c5fd;
        }

        .btn {
            display: block;
            width: 100%;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 1rem;
        }

        .btn:hover {
            background: #2563eb;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>Track Your Payment</h1>
        <p>Enter your payment reference ID to check the latest status.</p>

        @if (session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        <form method="GET" action="{{ route('payment-gateway.status.search') }}">
            <div class="form-group">
                <label for="reference">Payment Reference ID</label>
                <input type="text" id="reference" name="reference" placeholder="e.g. ORD-2024-001" required
                    value="{{ request('reference') }}">
            </div>
            <button type="submit" class="btn">Check Status</button>
        </form>
    </div>
</body>

</html>
