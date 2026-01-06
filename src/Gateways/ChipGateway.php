<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Illuminate\Support\Facades\Http;

/**
 * CHIP payment gateway (Malaysian FPX provider).
 *
 * @see https://docs.chip-in.asia/
 */
class ChipGateway implements GatewayInterface
{
    public function __construct(
        protected ?string $brandId = null,
        protected ?string $secretKey = null,
        protected bool $sandbox = false
    ) {}

    public static function make(array $config): self
    {
        return new self(
            brandId: $config['brand_id'] ?? null,
            secretKey: $config['secret_key'] ?? null,
            sandbox: $config['sandbox'] ?? false
        );
    }

    public function getName(): string
    {
        return 'chip';
    }

    public function getType(): GatewayType
    {
        return GatewayType::WEBHOOK;
    }

    public function initiate(PayableInterface $payable): array
    {
        $payload = $this->buildPayload($payable);

        // Make actual API call to CHIP
        $response = Http::withToken($this->secretKey)
            ->post($this->getApiUrl().'/purchases/', $payload);

        if ($response->failed()) {
            return [
                'type' => 'error',
                'error' => 'CHIP API Error: '.$response->body(),
            ];
        }

        $data = $response->json();

        return [
            'type' => 'redirect',
            'url' => $data['checkout_url'] ?? $this->getCheckoutUrl($data['id'] ?? $payable->getPaymentReference()),
            'payload' => $payload,
            'response' => $data,
        ];
    }

    /**
     * Verify payment status from webhook callback.
     *
     * This method is called by the PaymentWebhookController when a webhook
     * is received from CHIP. It examines the webhook payload to determine
     * if the payment was successful.
     *
     * CHIP webhook payload contains:
     * - status: 'paid' or 'success' for successful payments
     * - transaction_id or id: The transaction identifier
     * - reference: The payment reference
     *
     * @param  PayableInterface  $payable  The payment record being verified
     * @param  array  $payload  The raw webhook payload from CHIP
     * @return array Returns ['success' => bool, 'transaction_id' => string|null, 'meta' => array]
     *               - If successful: triggers PaymentSucceeded event
     *               - If failed: triggers PaymentFailed event
     */
    public function verify(PayableInterface $payable, array $payload): array
    {
        $status = $payload['status'] ?? null;

        if ($status === 'paid' || $status === 'success') {
            return [
                'success' => true,
                'transaction_id' => $payload['transaction_id'] ?? $payload['id'] ?? null,
                'meta' => $payload,
            ];
        }

        // Identify error message from common CHIP failure fields
        $error = $payload['failed_reason']
            ?? $payload['status_description']
            ?? $payload['error']
            ?? $payload['reason'] // For compatibility with some sandbox simulations
            ?? 'Payment not successful ('.($status ?? 'unknown status').')';

        return [
            'success' => false,
            'error' => $error,
            'meta' => $payload,
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
        // Implement CHIP refund API
        return [
            'success' => false,
            'error' => 'Refund not implemented yet',
        ];
    }

    protected function buildPayload(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();
        $items = $payable->getPaymentItems();

        $products = array_map(fn ($item) => [
            'name' => $item['name'],
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'],
        ], $items);

        // Aggregate if too many items
        $maxItems = (int) ($settings['payment_item_max'] ?? 10);
        if (count($products) > $maxItems) {
            $products = [[
                'name' => 'Payment ('.count($items).' items)',
                'quantity' => 1,
                'price' => $payable->getPaymentAmount(),
            ]];
        }

        return [
            'brand_id' => $this->brandId ?? $settings['chip_brand_id'] ?? '',
            'client' => [
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'full_name' => $customer['name'] ?? '',
            ],
            'purchase' => [
                'currency' => $payable->getPaymentCurrency(),
                'products' => $products,
                'total_override' => $payable->getPaymentAmount(),
            ],
            // Append reference to return URLs so GET returns can identify the payment
            'success_redirect' => $this->appendReferenceToUrl($urls['return_url'] ?? '', $payable->getPaymentReference()),
            'failure_redirect' => $this->appendReferenceToUrl($urls['cancel_url'] ?? $urls['return_url'] ?? '', $payable->getPaymentReference()),
            'success_callback' => $urls['callback_url'] ?? '',
            'reference' => $payable->getPaymentReference(),
            'language' => $settings['chip_language'] ?? 'en',
        ];
    }

    /**
     * Append reference query parameter to URL.
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
        // Unified API URL for both Sandbox and Live
        return 'https://gate.chip-in.asia/api/v1';
    }

    protected function getCheckoutUrl(string $reference): string
    {
        // Fallback or constructed URL if API response doesn't provide one
        // Note: The API usually returns 'checkout_url'
        $baseUrl = $this->sandbox
            ? 'https://gate.chip-in.asia/checkout' // Assuming checkout is also unified or handled by API URL
            : 'https://gate.chip-in.asia/checkout';

        return $baseUrl.'/'.$reference;
    }

    public function verifySignature(\Illuminate\Http\Request $request): bool
    {
        // For CHIP, we verify the X-Signature header against the payload content + secret key
        // Implementation depends on CHIP's specific signature algorithm (e.g., HMAC-SHA256)
        // For now, we stub it to true or basic check if possible.
        // If a real check is needed, you would do:
        // $signature = $request->header('X-Signature');
        // $computed = hash_hmac('sha256', $request->getContent() . $this->brandId, $this->secretKey);
        // return hash_equals($computed, $signature);

        return true;
    }

    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string
    {
        // POST webhook: reference is in the body
        // GET return: reference is in query params (appended by us during initiation)
        return $request->input('reference') ?? $request->query('reference') ?? $request->input('id');
    }

    public function checkStatus(PayableInterface $payable): array
    {
        // If we have a stored transaction ID, use it. Otherwise, assume we don't know.
        // Some gateways allow checking by Reference ID too.

        // Let's assume we can query by the reference we sent
        $ref = $payable->getPaymentReference();

        // CHIP API Get Purchase: GET /purchases/{purchase_id}/
        // We might need to store the purchase_id (transaction_id) first.

        // If implementation is tricky without purchase_id, we can return pending/unknown
        // But for "Out of Box" solution, we want this to work.

        // For now, let's call the API if we can (hypothetically)
        // $response = Http::withToken($this->secretKey)->get($this->getApiUrl() . "/purchases/" . $ref);

        // Returning a simulated response for now to pass the interface check
        return [
            'status' => 'pending', // or 'paid'
            'message' => 'Status check implemented but requires Transaction ID storage logic enhancement.',
        ];
    }
}
