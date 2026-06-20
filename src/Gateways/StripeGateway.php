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
 * Stripe payment gateway.
 *
 * Uses Stripe Checkout for redirect-based payments.
 *
 * @see https://stripe.com/docs/api
 */
class StripeGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'stripe';
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
        $settings = $payable->getPaymentSettings();
        $secretKey = $this->secretKey($settings);
        $payload = $this->buildCheckoutPayload($payable);

        $response = $this->http()->withBasicAuth($secretKey, '')
            ->asForm()
            ->post($this->getApiUrl().'/checkout/sessions', $payload);

        if ($response->successful()) {
            $session = $response->json();

            return $this->redirect(
                url: $session['url'],
                transactionId: $session['id'] ?? null,
                payload: $payload,
                response: $session,
                extra: ['session_id' => $session['id'] ?? null],
            );
        }

        $responseData = $response->json() ?: ['body' => $response->body()];

        return $this->fail(
            $responseData['error']['message'] ?? 'Failed to create checkout session',
            $payload,
            $responseData,
        );
    }

    /**
     * Verify a payment from either a return URL (session_id) or a webhook event.
     *
     * - Return URL: pass ['session_id' => 'cs_xxx'] and Stripe is queried by API.
     * - Webhook: pass the event payload (with a 'type' field).
     */
    public function verify(PayableInterface $payable, array $payload): VerificationResult
    {
        if (isset($payload['session_id']) && ! isset($payload['type'])) {
            return $this->verifyBySessionId($payable, $payload['session_id']);
        }

        return $this->verifyWebhookEvent($payload);
    }

    protected function verifyBySessionId(PayableInterface $payable, string $sessionId): VerificationResult
    {
        $secretKey = $this->secretKey($payable->getPaymentSettings());

        $response = $this->http()->withBasicAuth($secretKey, '')
            ->get($this->getApiUrl().'/checkout/sessions/'.$sessionId);

        if ($response->failed()) {
            return $this->rejected(
                'Failed to retrieve session from Stripe: '.$response->body(),
                ['session_id' => $sessionId],
            );
        }

        $session = $response->json();
        $paymentStatus = $session['payment_status'] ?? null;

        if ($paymentStatus === 'paid' || $paymentStatus === 'no_payment_required') {
            return $this->verified($session['payment_intent'] ?? $session['id'] ?? null, $session);
        }

        return $this->rejected('Payment not completed - status: '.($paymentStatus ?? 'unknown'), $session);
    }

    protected function verifyWebhookEvent(array $payload): VerificationResult
    {
        $eventType = $payload['type'] ?? null;

        if ($eventType === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $paymentStatus = $session['payment_status'] ?? null;

            if ($paymentStatus === 'paid' || $paymentStatus === 'no_payment_required') {
                return $this->verified($session['payment_intent'] ?? $session['id'] ?? null, $payload);
            }

            return $this->rejected('Payment pending - status: '.($paymentStatus ?? 'unknown'), $payload);
        }

        if ($eventType === 'payment_intent.succeeded') {
            $intent = $payload['data']['object'] ?? [];

            return $this->verified($intent['id'] ?? null, $payload);
        }

        if ($eventType === 'payment_intent.payment_failed') {
            $intent = $payload['data']['object'] ?? [];

            return $this->rejected($intent['last_payment_error']['message'] ?? 'Payment failed', $payload);
        }

        return $this->rejected('Unhandled event type: '.($eventType ?? 'unknown'), $payload);
    }

    public function refund(string $transactionId, ?int $amount = null): VerificationResult
    {
        $secretKey = $this->secretKey();

        $payload = ['payment_intent' => $transactionId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = $this->http()->withBasicAuth($secretKey, '')
            ->asForm()
            ->post($this->getApiUrl().'/refunds', $payload);

        if ($response->successful()) {
            $data = $response->json();

            return $this->verified($data['id'] ?? null, $data);
        }

        return $this->rejected($response->json()['error']['message'] ?? 'Refund failed');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCheckoutPayload(PayableInterface $payable): array
    {
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();
        $reference = $payable->getPaymentReference();

        // Single line at the authoritative total — Stripe charges exactly this,
        // never a recomputed sum of itemised lines.
        return [
            'line_items[0][price_data][currency]' => strtolower($payable->getPaymentCurrency()),
            'line_items[0][price_data][product_data][name]' => LineItems::summaryName($payable),
            'line_items[0][price_data][unit_amount]' => $payable->getPaymentAmount(),
            'line_items[0][quantity]' => 1,
            'mode' => 'payment',
            'success_url' => ($urls['return_url'] ?? '').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $urls['cancel_url'] ?? $urls['return_url'] ?? '',
            'client_reference_id' => $reference,
            'customer_email' => $customer['email'] ?? null,
            // Attach reference to metadata so PaymentIntent events can also identify the order.
            'metadata[reference]' => $reference,
            'payment_intent_data[metadata][reference]' => $reference,
        ];
    }

    protected function getApiUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    public function verifySignature(Request $request): bool
    {
        $webhookSecret = $this->webhookSecret();

        // Skip verification if no secret configured (development only).
        if (empty($webhookSecret)) {
            Log::warning('Stripe webhook signature verification skipped - no secret configured');

            return true;
        }

        $signature = $request->header('Stripe-Signature');
        if (! $signature) {
            Log::error('Stripe webhook: Missing Stripe-Signature header');

            return false;
        }

        $payload = $request->getContent();

        try {
            // Parse signature header. Format: t=timestamp,v1=signature1,v1=signature2
            $elements = explode(',', $signature);
            $timestamp = null;
            $signatures = [];

            foreach ($elements as $element) {
                $parts = explode('=', $element, 2);
                if (count($parts) === 2) {
                    if ($parts[0] === 't') {
                        $timestamp = $parts[1];
                    } elseif ($parts[0] === 'v1') {
                        $signatures[] = $parts[1];
                    }
                }
            }

            if (! $timestamp || empty($signatures)) {
                Log::error('Stripe webhook: Invalid signature format');

                return false;
            }

            // Verify timestamp is recent (within 5 minutes).
            if (abs(time() - (int) $timestamp) > 300) {
                Log::error('Stripe webhook: Timestamp too old or too far in future');

                return false;
            }

            $expectedSignature = Signature::hmac("{$timestamp}.{$payload}", $webhookSecret);

            foreach ($signatures as $sig) {
                if (Signature::equals($expectedSignature, $sig)) {
                    return true;
                }
            }

            Log::error('Stripe webhook: Signature verification failed');

            return false;
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification error: '.$e->getMessage());

            return false;
        }
    }

    public function getPaymentIdFromRequest(Request $request): ?string
    {
        // Mode 1: Return URL callback (session_id in query string).
        if ($request->has('session_id') && ! $request->has('type')) {
            $response = $this->http()->withBasicAuth($this->secretKey(), '')
                ->get($this->getApiUrl().'/checkout/sessions/'.$request->input('session_id'));

            if ($response->successful()) {
                $data = $response->json();

                return $data['client_reference_id'] ?? $data['metadata']['reference'] ?? null;
            }

            return null;
        }

        // Mode 2: Webhook callback (Checkout Session, falling back to PaymentIntent metadata).
        return $request->input('data.object.client_reference_id')
            ?? $request->input('data.object.metadata.reference');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        $id = $this->transactionId($payable); // the checkout session id

        if (! $id) {
            return $this->statusResult(PaymentStatus::PENDING, 'No Stripe session id stored yet.');
        }

        $response = $this->http()->withBasicAuth($this->secretKey($payable->getPaymentSettings()), '')
            ->get($this->getApiUrl().'/checkout/sessions/'.$id);

        if ($response->failed()) {
            return $this->statusResult(PaymentStatus::PENDING, 'Could not retrieve status from Stripe.', $id);
        }

        $session = $response->json();
        $paymentStatus = $session['payment_status'] ?? null;

        if (in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            return $this->statusResult(PaymentStatus::PAID, 'Payment confirmed by Stripe.', $session['payment_intent'] ?? $id);
        }

        if (($session['status'] ?? null) === 'expired') {
            return $this->statusResult(PaymentStatus::FAILED, 'Checkout session expired.', $id);
        }

        return $this->statusResult(PaymentStatus::PENDING, 'Payment still pending.', $id);
    }

    protected function secretKey(array $settings = []): string
    {
        return (string) $this->setting('secret_key', '', $settings);
    }

    protected function webhookSecret(): string
    {
        return (string) $this->setting('webhook_secret', '');
    }
}
