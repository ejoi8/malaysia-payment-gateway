<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Ejoi8\MalaysiaPaymentGateway\Support\LineItems;
use Ejoi8\MalaysiaPaymentGateway\Support\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CHIP payment gateway (Malaysian FPX provider).
 *
 * Amounts are sent in cents (the package convention), which matches CHIP's API.
 *
 * @see https://docs.chip-in.asia/
 */
class ChipGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'chip';
    }

    public function getType(): GatewayType
    {
        return GatewayType::WEBHOOK;
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function initiate(PayableInterface $payable): PaymentResponse
    {
        $settings = $payable->getPaymentSettings();
        $payload = $this->buildPayload($payable);
        $secretKey = (string) $this->setting('secret_key', '', $settings);

        $response = $this->http()->withToken($secretKey)
            ->post($this->getApiUrl().'/purchases/', $payload);

        if ($response->failed()) {
            return $this->fail(
                'CHIP API Error: '.$response->body(),
                $payload,
                $response->json() ?: ['body' => $response->body()],
            );
        }

        $data = $response->json();
        $checkoutUrl = $data['checkout_url'] ?? null;

        if (! $checkoutUrl) {
            return $this->fail('CHIP did not return a checkout URL', $payload, $data ?: []);
        }

        return $this->redirect(
            url: $checkoutUrl,
            transactionId: $data['id'] ?? null,
            payload: $payload,
            response: $data,
        );
    }

    /**
     * Verify payment status from a CHIP webhook/return payload.
     *
     * CHIP marks a payment paid with status 'paid'; the purchase id is the
     * top-level 'id'. Failures carry a reason in one of several fields.
     */
    public function verify(PayableInterface $payable, array $payload): VerificationResult
    {
        $status = $payload['status'] ?? null;

        if ($status === 'paid' || $status === 'success') {
            // Real CHIP webhooks carry the purchase id in `id`; `transaction_id`
            // is kept as a fallback for callers/simulations that send it.
            return $this->verified($payload['transaction_id'] ?? $payload['id'] ?? null, $payload);
        }

        $error = $payload['failed_reason']
            ?? $payload['status_description']
            ?? $payload['error']
            ?? $payload['reason']
            ?? 'Payment not successful ('.($status ?? 'unknown status').')';

        return $this->rejected($error, $payload);
    }

    public function refund(string $transactionId, ?int $amount = null): VerificationResult
    {
        $secretKey = (string) $this->setting('secret_key', '');
        $payload = $amount !== null ? ['amount' => $amount] : [];

        $response = $this->http()->withToken($secretKey)
            ->post($this->getApiUrl()."/purchases/{$transactionId}/refund/", $payload);

        if ($response->successful()) {
            $data = $response->json();

            return $this->verified($data['id'] ?? $transactionId, $data);
        }

        $body = $response->json() ?: [];

        return $this->rejected(
            $body['__all__'][0]['message'] ?? $body['message'] ?? 'Refund failed',
            $body,
        );
    }

    /**
     * Verify the CHIP webhook signature.
     *
     * CHIP signs the raw body with RSA-SHA256 and sends the base64-encoded
     * signature in the X-Signature header. Configure the PEM public key
     * (config: gateways.chip.public_key, fetched once from GET /public_key/).
     * When no key is configured, verification is skipped and logged.
     */
    public function verifySignature(Request $request): bool
    {
        $publicKey = (string) $this->setting('public_key', '');

        if ($publicKey === '') {
            Log::warning('CHIP webhook signature verification skipped - no public_key configured');

            return true;
        }

        $signature = $request->header('X-Signature');

        if (! $signature) {
            Log::error('CHIP webhook: missing X-Signature header');

            return false;
        }

        return Signature::rsaVerify($request->getContent(), base64_decode($signature), $publicKey);
    }

    public function getPaymentIdFromRequest(Request $request): ?string
    {
        // POST webhook: reference is in the body.
        // GET return: reference is in query params (appended during initiation).
        return $request->input('reference') ?? $request->query('reference') ?? $request->input('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        $id = $this->transactionId($payable);

        if (! $id) {
            return $this->statusResult(PaymentStatus::PENDING, 'No CHIP purchase id stored yet.');
        }

        $secretKey = (string) $this->setting('secret_key', '', $payable->getPaymentSettings());
        $response = $this->http()->withToken($secretKey)->get($this->getApiUrl()."/purchases/{$id}/");

        if ($response->failed()) {
            return $this->statusResult(PaymentStatus::PENDING, 'Could not retrieve status from CHIP.', $id);
        }

        $status = $response->json()['status'] ?? null;

        return match ($status) {
            'paid' => $this->statusResult(PaymentStatus::PAID, 'Payment confirmed by CHIP.', $id),
            'refunded' => $this->statusResult(PaymentStatus::REFUNDED, 'Payment refunded.', $id),
            'cancelled', 'expired', 'error' => $this->statusResult(PaymentStatus::FAILED, 'Payment '.$status.'.', $id),
            default => $this->statusResult(PaymentStatus::PENDING, 'Payment still pending ('.($status ?? 'unknown').').', $id),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();

        return [
            'brand_id' => $this->setting('brand_id', '', $settings),
            'client' => [
                'email' => $customer['email'] ?? '',
                'phone' => $customer['phone'] ?? '',
                'full_name' => $customer['name'] ?? '',
            ],
            'purchase' => [
                'currency' => $payable->getPaymentCurrency(),
                // Single line at the authoritative total; total_override guarantees the charge.
                'products' => [[
                    'name' => LineItems::summaryName($payable),
                    'quantity' => 1,
                    'price' => $payable->getPaymentAmount(),
                ]],
                'total_override' => $payable->getPaymentAmount(),
                // CHIP expects `language` inside the purchase object (ISO 639-1).
                'language' => $settings['language'] ?? $settings['chip_language'] ?? 'en',
            ],
            'success_redirect' => $this->appendReference($urls['return_url'] ?? '', $payable->getPaymentReference()),
            'failure_redirect' => $this->appendReference($urls['cancel_url'] ?? $urls['return_url'] ?? '', $payable->getPaymentReference()),
            'success_callback' => $urls['callback_url'] ?? '',
            'reference' => $payable->getPaymentReference(),
        ];
    }

    protected function getApiUrl(): string
    {
        // Unified API URL for both Sandbox and Live (sandbox is selected by key).
        return 'https://gate.chip-in.asia/api/v1';
    }
}
