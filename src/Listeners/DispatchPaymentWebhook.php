<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentFailed;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentRefunded;
use Ejoi8\MalaysiaPaymentGateway\Events\PaymentSucceeded;
use Ejoi8\MalaysiaPaymentGateway\Support\Signature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forward verified payment events to the merchant's own backend as a signed
 * server-to-server webhook — the mirror image of how gateways notify us.
 *
 * Enabled by configuring outgoing_webhook.url. When a secret is set, the POST
 * carries an `X-Payment-Signature: hmac_sha256(rawBody, secret)` header so the
 * receiver can verify authenticity. Delivery failures are logged, never thrown.
 */
class DispatchPaymentWebhook
{
    /**
     * @param  object  $event
     */
    public function handle($event): void
    {
        $config = config('payment-gateway.outgoing_webhook');
        $url = $config['url'] ?? null;

        if (empty($url)) {
            return;
        }

        $payload = $this->buildPayload($event);

        if ($payload === null) {
            return;
        }

        $secret = (string) ($config['secret'] ?? '');

        if (! empty($config['queue'])) {
            dispatch(function () use ($url, $secret, $payload) {
                self::deliver($url, $secret, $payload);
            });

            return;
        }

        self::deliver($url, $secret, $payload);
    }

    /**
     * Build the normalized event payload, or null for events we don't forward.
     *
     * @return array<string, mixed>|null
     */
    protected function buildPayload(object $event): ?array
    {
        if ($event instanceof PaymentSucceeded) {
            return $this->payablePayload('payment.succeeded', $event->payable, $event->gateway, [
                'transaction_id' => $event->transactionId,
                'meta' => $event->meta,
            ]);
        }

        if ($event instanceof PaymentFailed) {
            return $this->payablePayload('payment.failed', $event->payable, $event->gateway, [
                'error' => $event->error,
                'meta' => $event->meta,
            ]);
        }

        if ($event instanceof PaymentRefunded) {
            return [
                'event' => 'payment.refunded',
                'gateway' => $event->gateway,
                'transaction_id' => $event->transactionId,
                'amount' => $event->amount,
                'meta' => $event->meta,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function payablePayload(string $name, PayableInterface $payable, string $gateway, array $extra): array
    {
        return array_merge([
            'event' => $name,
            'gateway' => $gateway,
            'reference' => $payable->getPaymentReference(),
            'amount' => $payable->getPaymentAmount(),
            'currency' => $payable->getPaymentCurrency(),
        ], $extra);
    }

    /**
     * Deliver the signed payload. Static so it can be queued without
     * serializing the listener instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function deliver(string $url, string $secret, array $payload): void
    {
        try {
            $body = json_encode($payload);

            $headers = [];
            if ($secret !== '') {
                $headers['X-Payment-Signature'] = Signature::hmac($body, $secret);
            }

            Http::timeout((int) config('payment-gateway.http.timeout', 30))
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            Log::error('Outgoing payment webhook failed.', [
                'url' => $url,
                'event' => $payload['event'] ?? null,
                'error' => $e->getMessage(),
            ]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        }
    }
}
