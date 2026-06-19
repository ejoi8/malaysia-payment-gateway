<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Ejoi8\MalaysiaPaymentGateway\Support\LineItems;
use Ejoi8\MalaysiaPaymentGateway\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayPal payment gateway.
 *
 * Uses PayPal Checkout (Orders API v2).
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
class PayPalGateway extends AbstractGateway
{
    protected ?string $accessToken = null;

    public function getName(): string
    {
        return 'paypal';
    }

    public function getType(): GatewayType
    {
        return GatewayType::API;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function initiate(PayableInterface $payable): PaymentResponse
    {
        $payload = $this->buildOrderPayload($payable);
        $accessToken = $this->getAccessToken($payable);

        if (! $accessToken) {
            return $this->fail('Failed to authenticate with PayPal', $payload);
        }

        $response = $this->http()->withToken($accessToken)
            ->post($this->getApiUrl().'/v2/checkout/orders', $payload);

        if ($response->successful()) {
            $order = $response->json();
            $links = collect($order['links'] ?? []);

            // 'approve' (application_context flow) or 'payer-action'
            // (payment_source.experience_context flow).
            $approveLink = $links->firstWhere('rel', 'approve')
                ?? $links->firstWhere('rel', 'payer-action');
            $url = $approveLink['href'] ?? '';

            if ($url === '') {
                return $this->fail('PayPal did not return an approval URL', $payload, $order, ['order_id' => $order['id'] ?? null]);
            }

            return $this->redirect(
                url: $url,
                transactionId: $order['id'] ?? null,
                payload: $payload,
                response: $order,
                extra: ['order_id' => $order['id'] ?? null],
            );
        }

        $responseData = $response->json() ?: ['body' => $response->body()];

        return $this->fail(
            $responseData['message'] ?? 'Failed to create PayPal order',
            $payload,
            $responseData,
            ['details' => $responseData],
        );
    }

    /**
     * Verify a PayPal payment.
     *
     * PayPal requires the order to be captured before it's finalized: we read
     * the order id from the return/webhook, capture it, and treat a 'COMPLETED'
     * status as success.
     */
    public function verify(PayableInterface $payable, array $payload): VerificationResult
    {
        $orderId = $payload['orderID'] ?? $payload['token'] ?? $payload['order_id'] ?? null;

        if (! $orderId) {
            return $this->rejected('No order ID provided', $payload);
        }

        $accessToken = $this->getAccessToken($payable);

        if (! $accessToken) {
            return $this->rejected('Failed to get PayPal access token', $payload);
        }

        $captureUrl = $this->getApiUrl()."/v2/checkout/orders/{$orderId}/capture";

        Log::debug('PayPal capture attempt', ['order_id' => $orderId, 'url' => $captureUrl]);

        // PayPal requires an empty JSON object as the capture body.
        $response = $this->http()->withToken($accessToken)->asJson()->post($captureUrl, new \stdClass);
        $responseData = $response->json();

        Log::debug('PayPal capture response', ['order_id' => $orderId, 'status_code' => $response->status()]);

        if ($response->successful() && ($responseData['status'] ?? null) === 'COMPLETED') {
            $capture = $responseData['purchase_units'][0]['payments']['captures'][0] ?? [];

            // The persisted transaction id must be the CAPTURE id (refunds
            // operate on it). Falling back to the order id would make a later
            // refund target the wrong resource, so warn loudly if it's missing.
            if (empty($capture['id'])) {
                Log::warning('PayPal order COMPLETED but capture id missing; refunds may fail', ['order_id' => $orderId]);
            }

            return $this->verified($capture['id'] ?? $orderId, $responseData);
        }

        $errorMessage = $responseData['message']
            ?? $responseData['details'][0]['description']
            ?? $responseData['error_description']
            ?? 'Payment capture failed';

        Log::error('PayPal capture failed', [
            'order_id' => $orderId,
            'status_code' => $response->status(),
            'error' => $errorMessage,
        ]);

        return $this->rejected($errorMessage, $responseData ?? []);
    }

    public function refund(string $transactionId, ?int $amount = null): VerificationResult
    {
        $accessToken = $this->getAccessTokenFromConfig($this->config);

        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = [
                'value' => Money::toDecimal($amount),
                'currency_code' => $this->setting('currency', 'USD'),
            ];
        }

        $response = $this->http()->withToken($accessToken)
            ->post($this->getApiUrl()."/v2/payments/captures/{$transactionId}/refund", $payload);

        if ($response->successful()) {
            $data = $response->json();

            return $this->verified($data['id'] ?? null, $data);
        }

        return $this->rejected($response->json()['message'] ?? 'Refund failed');
    }

    /**
     * Verify a PayPal webhook via the verify-webhook-signature API.
     *
     * Requires a configured webhook_id (gateways.paypal.webhook_id) from the
     * PayPal dashboard. When absent, verification is skipped and logged. Note
     * the GET return-capture flow does not pass through here.
     */
    public function verifySignature(Request $request): bool
    {
        $webhookId = (string) $this->setting('webhook_id', '');

        if ($webhookId === '') {
            Log::warning('PayPal webhook signature verification skipped - no webhook_id configured');

            return true;
        }

        $accessToken = $this->getAccessTokenFromConfig($this->config);

        if (! $accessToken) {
            return false;
        }

        $response = $this->http()->withToken($accessToken)
            ->post($this->getApiUrl().'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('paypal-auth-algo'),
                'cert_url' => $request->header('paypal-cert-url'),
                'transmission_id' => $request->header('paypal-transmission-id'),
                'transmission_sig' => $request->header('paypal-transmission-sig'),
                'transmission_time' => $request->header('paypal-transmission-time'),
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($request->getContent(), true),
            ]);

        return ($response->json()['verification_status'] ?? null) === 'SUCCESS';
    }

    public function getPaymentIdFromRequest(Request $request): ?string
    {
        // GET return: reference is in query params (appended during initiation).
        if ($request->has('reference')) {
            return $request->query('reference');
        }

        // POST webhook: resource object carries our reference in purchase_units.
        return $request->input('resource.purchase_units.0.reference_id')
            ?? $request->input('resource.id');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        $id = $this->transactionId($payable); // the order id

        if (! $id) {
            return $this->statusResult(PaymentStatus::PENDING, 'No PayPal order id stored yet.');
        }

        $accessToken = $this->getAccessTokenFromConfig($payable->getPaymentSettings());

        if (! $accessToken) {
            return $this->statusResult(PaymentStatus::PENDING, 'Could not authenticate with PayPal.', $id);
        }

        $response = $this->http()->withToken($accessToken)->get($this->getApiUrl()."/v2/checkout/orders/{$id}");

        if ($response->failed()) {
            return $this->statusResult(PaymentStatus::PENDING, 'Could not retrieve status from PayPal.', $id);
        }

        $status = $response->json()['status'] ?? null;

        return match ($status) {
            'COMPLETED' => $this->statusResult(PaymentStatus::PAID, 'Payment completed.', $id),
            'VOIDED' => $this->statusResult(PaymentStatus::FAILED, 'Order voided.', $id),
            default => $this->statusResult(PaymentStatus::PENDING, 'Payment still pending ('.($status ?? 'unknown').').', $id),
        };
    }

    protected function getAccessToken(PayableInterface $payable): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        return $this->getAccessTokenFromConfig($payable->getPaymentSettings());
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function getAccessTokenFromConfig(array $settings): ?string
    {
        $clientId = $this->setting('client_id', '', $settings);
        $clientSecret = $this->setting('client_secret', '', $settings);

        $response = $this->http()->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->getApiUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($response->successful()) {
            return $this->accessToken = $response->json()['access_token'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOrderPayload(PayableInterface $payable): array
    {
        $urls = $payable->getPaymentUrls();
        $currency = $payable->getPaymentCurrency();
        $reference = $payable->getPaymentReference();

        // Just the authoritative total — no items/breakdown, so PayPal has
        // nothing to reconcile and can never return ITEM_TOTAL_MISMATCH.
        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'description' => LineItems::summaryName($payable),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => Money::toDecimal($payable->getPaymentAmount()),
                ],
            ]],
            'application_context' => [
                'return_url' => $this->appendReference($urls['return_url'] ?? '', $reference),
                'cancel_url' => $this->appendReference($urls['cancel_url'] ?? $urls['return_url'] ?? '', $reference),
                'brand_name' => $payable->getPaymentSettings()['brand_name'] ?? 'Payment',
                'user_action' => 'PAY_NOW',
            ],
        ];
    }

    protected function getApiUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    protected function isSandbox(): bool
    {
        return (bool) $this->setting('sandbox', false);
    }
}
