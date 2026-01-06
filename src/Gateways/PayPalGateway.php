<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Illuminate\Support\Facades\Http;

/**
 * PayPal payment gateway.
 *
 * Uses PayPal Checkout (Orders API v2).
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
class PayPalGateway implements GatewayInterface
{
    protected ?string $accessToken = null;

    public function __construct(
        protected ?string $clientId = null,
        protected ?string $clientSecret = null,
        protected bool $sandbox = false
    ) {}

    public static function make(array $config): self
    {
        return new self(
            clientId: $config['client_id'] ?? null,
            clientSecret: $config['client_secret'] ?? null,
            sandbox: $config['sandbox'] ?? false
        );
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function getType(): GatewayType
    {
        return GatewayType::API;
    }

    public function initiate(PayableInterface $payable): array
    {
        $accessToken = $this->getAccessToken($payable);

        if (! $accessToken) {
            return [
                'type' => 'error',
                'error' => 'Failed to authenticate with PayPal',
            ];
        }

        $payload = $this->buildOrderPayload($payable);

        $response = Http::withToken($accessToken)
            ->post($this->getApiUrl().'/v2/checkout/orders', $payload);

        if ($response->successful()) {
            $order = $response->json();
            $approveLink = collect($order['links'] ?? [])
                ->firstWhere('rel', 'approve');

            return [
                'type' => 'redirect',
                'url' => $approveLink['href'] ?? '',
                'order_id' => $order['id'],
                'payload' => $payload,
            ];
        }

        return [
            'type' => 'error',
            'error' => $response->json()['message'] ?? 'Failed to create PayPal order',
            'details' => $response->json(),
        ];
    }

    /**
     * Verify payment status from webhook callback.
     *
     * This method is called by the PaymentWebhookController when a webhook
     * is received from PayPal. Unlike other gateways, PayPal requires an
     * additional API call to "capture" the order before it's finalized.
     *
     * PayPal webhook flow:
     * 1. Webhook arrives with orderID
     * 2. We call PayPal API to capture the order
     * 3. If capture succeeds and status is 'COMPLETED', payment is successful
     *
     * PayPal webhook payload contains:
     * - orderID or order_id: The PayPal order identifier
     * - event_type: Event type (e.g., 'PAYMENT.CAPTURE.COMPLETED')
     * - resource.id: The capture/transaction ID
     *
     * @param  PayableInterface  $payable  The payment record being verified
     * @param  array  $payload  The raw webhook payload from PayPal
     * @return array Returns ['success' => bool, 'transaction_id' => string|null, 'meta' => array]
     *               - If successful: triggers PaymentSucceeded event
     *               - If failed: triggers PaymentFailed event
     */
    public function verify(PayableInterface $payable, array $payload): array
    {
        // PayPal return URL sends 'orderID' or 'token' as query parameter
        // PayPal webhooks send event data with 'resource' object
        $orderId = $payload['orderID'] ?? $payload['token'] ?? $payload['order_id'] ?? null;

        if (! $orderId) {
            return [
                'success' => false,
                'error' => 'No order ID provided',
                'meta' => $payload,
            ];
        }

        $accessToken = $this->getAccessToken($payable);

        if (! $accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to get PayPal access token',
                'meta' => $payload,
            ];
        }

        // Capture the order - PayPal requires empty JSON object {} as body
        $captureUrl = $this->getApiUrl()."/v2/checkout/orders/{$orderId}/capture";

        \Log::info('PayPal: Attempting to capture order', [
            'order_id' => $orderId,
            'url' => $captureUrl,
        ]);

        $response = Http::withToken($accessToken)
            ->asJson()
            ->post($captureUrl, new \stdClass);

        $responseData = $response->json();

        \Log::info('PayPal: Capture response', [
            'status_code' => $response->status(),
            'response' => $responseData,
        ]);

        if ($response->successful()) {
            $status = $responseData['status'] ?? null;

            if ($status === 'COMPLETED') {
                $capture = $responseData['purchase_units'][0]['payments']['captures'][0] ?? [];

                return [
                    'success' => true,
                    'transaction_id' => $capture['id'] ?? $orderId,
                    'meta' => $responseData,
                ];
            }
        }

        // Extract detailed error from PayPal response
        $errorMessage = $responseData['message']
            ?? $responseData['details'][0]['description']
            ?? $responseData['error_description']
            ?? 'Payment capture failed';

        // Log the full error for debugging
        \Log::error('PayPal: Capture failed', [
            'order_id' => $orderId,
            'error' => $errorMessage,
            'full_response' => $responseData,
        ]);

        return [
            'success' => false,
            'error' => $errorMessage,
            'meta' => $responseData,
        ];
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function refund(string $transactionId, ?int $amount = null): array
    {
        $settings = config('payment-gateway.gateways.paypal', []);
        $accessToken = $this->getAccessTokenFromConfig($settings);

        $payload = [];
        if ($amount !== null) {
            $payload['amount'] = [
                'value' => number_format($amount / 100, 2, '.', ''),
                'currency_code' => $settings['currency'] ?? 'USD',
            ];
        }

        $response = Http::withToken($accessToken)
            ->post($this->getApiUrl()."/v2/payments/captures/{$transactionId}/refund", $payload);

        if ($response->successful()) {
            return [
                'success' => true,
                'refund_id' => $response->json()['id'],
                'meta' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'error' => $response->json()['message'] ?? 'Refund failed',
        ];
    }

    protected function getAccessToken(PayableInterface $payable): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $settings = $payable->getPaymentSettings();

        return $this->getAccessTokenFromConfig($settings);
    }

    protected function getAccessTokenFromConfig(array $settings): ?string
    {
        $clientId = $this->clientId ?? $settings['paypal_client_id'] ?? '';
        $clientSecret = $this->clientSecret ?? $settings['paypal_client_secret'] ?? '';

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->getApiUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->successful()) {
            $this->accessToken = $response->json()['access_token'];

            return $this->accessToken;
        }

        return null;
    }

    protected function buildOrderPayload(PayableInterface $payable): array
    {
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();
        $items = $payable->getPaymentItems();
        $currency = $payable->getPaymentCurrency();

        $lineItems = array_map(fn ($item) => [
            'name' => $item['name'],
            'quantity' => (string) ($item['quantity'] ?? 1),
            'unit_amount' => [
                'currency_code' => $currency,
                'value' => number_format($item['price'] / 100, 2, '.', ''),
            ],
        ], $items);

        $totalValue = number_format($payable->getPaymentAmount() / 100, 2, '.', '');

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payable->getPaymentReference(),
                'description' => $payable->getPaymentDescription(),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $totalValue,
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => $currency,
                            'value' => $totalValue,
                        ],
                    ],
                ],
                'items' => $lineItems,
            ]],
            'application_context' => [
                // Append reference to return URL so GET returns can identify the payment
                'return_url' => $this->appendReferenceToUrl($urls['return_url'] ?? '', $payable->getPaymentReference()),
                'cancel_url' => $this->appendReferenceToUrl($urls['cancel_url'] ?? $urls['return_url'] ?? '', $payable->getPaymentReference()),
                'brand_name' => $payable->getPaymentSettings()['brand_name'] ?? 'Payment',
                'user_action' => 'PAY_NOW',
            ],
        ];
    }

    /**
     * Append reference to URL as query parameter.
     */
    protected function appendReferenceToUrl(string $url, string $reference): string
    {
        if (empty($url)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'reference='.$reference;
    }

    protected function getApiUrl(): string
    {
        return $this->sandbox
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function verifySignature(\Illuminate\Http\Request $request): bool
    {
        // PayPal Webhook Signature verification is complex (involves certs).
        // For MVP, we stub to true.
        // Real impl should use PayPal SDK or manual header verification.
        return true;
    }

    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string
    {
        // GET return: reference is in query params (appended by us during initiation)
        if ($request->has('reference')) {
            return $request->query('reference');
        }

        // POST webhook: resource object contains our reference in purchase_units
        $reference = $request->input('resource.purchase_units.0.reference_id');
        if ($reference) {
            return $reference;
        }

        // Fallback: resource ID (PayPal transaction ID)
        return $request->input('resource.id');
    }

    public function checkStatus(PayableInterface $payable): array
    {
        // Mock implementation for now
        // Needs Order ID to check status: GET /v2/checkout/orders/{id}
        return [
            'status' => 'pending',
            'message' => 'Status check implemented (Stub for PayPal).',
        ];
    }
}
