<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to payment…</title>
</head>
{{--
    Auto-submitting form for gateways that require a POST hand-off (e.g. iPay88,
    senangPay). Render it from your controller after initiate():

        $response = Payment::initiate('ipay88', $payment);

        if ($response->isFormPost()) {
            return view('payment-gateway::auto-submit', [
                'action' => $response->formAction(),
                'fields' => $response->formFields(),
                'method' => $response->formMethod(),
            ]);
        }
--}}
<body onload="document.getElementById('mpg-redirect-form').submit()">
    <p style="font-family: sans-serif; text-align: center; margin-top: 3rem;">
        Redirecting to the payment page…
    </p>

    <form id="mpg-redirect-form" action="{{ $action }}" method="{{ $method ?? 'POST' }}">
        @foreach ($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <noscript>
            <p style="text-align: center;">
                <button type="submit">Continue to payment</button>
            </p>
        </noscript>
    </form>
</body>
</html>
