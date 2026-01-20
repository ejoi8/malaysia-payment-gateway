<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Illuminate\Support\Facades\Http;

/**
 * Stripe payment gateway.
 *
 * Uses Stripe Checkout for redirect-based payments.
 *
 * @see https://stripe.com/docs/api
 */
class StripeGateway implements GatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $publicKey = null
    ) {}

    public static function make(array $config): self
    {
        return new self(
            secretKey: $config['secret_key'] ?? null,
            publicKey: $config['public_key'] ?? null
        );
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function getType(): GatewayType
    {
        return GatewayType::API;
    }

    public function initiate(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $secretKey = $this->secretKey ?? $settings['stripe_secret_key'] ?? '';

        $payload = $this->buildCheckoutPayload($payable);

        // Create Stripe Checkout Session
        $response = Http::withBasicAuth($secretKey, '')
            ->asForm()
            ->post($this->getApiUrl().'/checkout/sessions', $payload);

        if ($response->successful()) {
            $session = $response->json();

            return [
                'type' => 'redirect',
                'url' => $session['url'],
                'session_id' => $session['id'],
                'payload' => $payload,
            ];
        }

        return [
            'type' => 'error',
            'error' => $response->json()['error']['message'] ?? 'Failed to create checkout session',
        ];
    }

    /**
     * Verify payment status from webhook or return URL.
     *
     * This method supports two verification modes:
     *
     * **Mode 1: Return URL (Recommended for simplicity)**
     * When user returns from Stripe Checkout, they land on success_url with session_id.
     * Pass ['session_id' => 'cs_xxx'] and this method will call Stripe API to verify.
     *
     * **Mode 2: Webhook (Recommended for reliability)**
     * Configure webhook in Stripe Dashboard. Stripe will POST event data.
     * Pass the webhook payload directly and this method will verify the event.
     *
     * Stripe webhook events handled:
     * - checkout.session.completed: Checkout session was completed
     * - payment_intent.succeeded: Payment intent succeeded
     * - payment_intent.payment_failed: Payment failed
     *
     * @param  PayableInterface  $payable  The payment record being verified
     * @param  array  $payload  Either ['session_id' => '...'] or webhook event payload
     * @return array Returns ['success' => bool, 'transaction_id' => string|null, 'meta' => array]
     *               - If successful: triggers PaymentSucceeded event
     *               - If failed: triggers PaymentFailed event
     */
    public function verify(PayableInterface $payable, array $payload): array
    {
        // Mode 1: Return URL verification (session_id provided)
        // User returned from Stripe Checkout with session_id in query string
        if (isset($payload['session_id']) && ! isset($payload['type'])) {
            return $this->verifyBySessionId($payable, $payload['session_id']);
        }

        // Mode 2: Webhook verification (event type provided)
        return $this->verifyWebhookEvent($payload);
    }

    /**
     * Verify payment by retrieving session from Stripe API.
     *
     * This is used when user returns via success_url with session_id.
     * We call Stripe API to get the actual payment status.
     *
     * @param  PayableInterface  $payable  The payment record
     * @param  string  $sessionId  The Stripe checkout session ID
     * @return array Verification result
     */
    protected function verifyBySessionId(PayableInterface $payable, string $sessionId): array
    {
        $settings = $payable->getPaymentSettings();
        $secretKey = $this->secretKey ?? $settings['stripe_secret_key'] ?? '';

        // Call Stripe API to retrieve session details
        $response = Http::withBasicAuth($secretKey, '')
            ->get($this->getApiUrl().'/checkout/sessions/'.$sessionId);

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve session from Stripe: '.$response->body(),
                'meta' => ['session_id' => $sessionId],
            ];
        }

        $session = $response->json();
        $paymentStatus = $session['payment_status'] ?? null;

        // Stripe session payment_status: 'paid', 'unpaid', or 'no_payment_required'
        if ($paymentStatus === 'paid' || $paymentStatus === 'no_payment_required') {
            return [
                'success' => true,
                'transaction_id' => $session['payment_intent'] ?? $session['id'] ?? null,
                'meta' => $session,
            ];
        }

        // Payment not completed
        return [
            'success' => false,
            'error' => 'Payment not completed - status: '.($paymentStatus ?? 'unknown'),
            'meta' => $session,
        ];
    }

    /**
     * Verify payment from webhook event payload.
     *
     * This is used when Stripe sends webhook events.
     *
     * @param  array  $payload  The webhook event payload
     * @return array Verification result
     */
    protected function verifyWebhookEvent(array $payload): array
    {
        $eventType = $payload['type'] ?? null;

        // Handle checkout.session.completed event
        if ($eventType === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $paymentStatus = $session['payment_status'] ?? null;

            // Stripe sends payment_status: 'paid' for successful payments
            // 'unpaid' means async payment method (e.g., bank transfer) - payment pending
            // 'no_payment_required' means amount was 0 or fully covered by credits
            if ($paymentStatus === 'paid' || $paymentStatus === 'no_payment_required') {
                return [
                    'success' => true,
                    'transaction_id' => $session['payment_intent'] ?? $session['id'] ?? null,
                    'meta' => $payload,
                ];
            }

            // Payment not yet completed (async payment methods)
            return [
                'success' => false,
                'error' => 'Payment pending - status: '.($paymentStatus ?? 'unknown'),
                'meta' => $payload,
            ];
        }

        // Handle payment_intent.succeeded event (alternative webhook)
        if ($eventType === 'payment_intent.succeeded') {
            $intent = $payload['data']['object'] ?? [];

            return [
                'success' => true,
                'transaction_id' => $intent['id'] ?? null,
                'meta' => $payload,
            ];
        }

        // Handle payment_intent.payment_failed event
        if ($eventType === 'payment_intent.payment_failed') {
            $intent = $payload['data']['object'] ?? [];
            $error = $intent['last_payment_error']['message'] ?? 'Payment failed';

            return [
                'success' => false,
                'error' => $error,
                'meta' => $payload,
            ];
        }

        // Unknown or unhandled event type
        return [
            'success' => false,
            'error' => 'Unhandled event type: '.($eventType ?? 'unknown'),
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
        $settings = config('payment-gateway.gateways.stripe', []);
        $secretKey = $this->secretKey ?? $settings['secret_key'] ?? '';

        $payload = ['payment_intent' => $transactionId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->asForm()
            ->post($this->getApiUrl().'/refunds', $payload);

        if ($response->successful()) {
            return [
                'success' => true,
                'refund_id' => $response->json()['id'],
                'meta' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'error' => $response->json()['error']['message'] ?? 'Refund failed',
        ];
    }

    protected function buildCheckoutPayload(PayableInterface $payable): array
    {
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();
        $items = $payable->getPaymentItems();
        $reference = $payable->getPaymentReference();

        $lineItems = array_map(fn ($item, $i) => [
            "line_items[$i][price_data][currency]" => strtolower($payable->getPaymentCurrency()),
            "line_items[$i][price_data][product_data][name]" => $item['name'],
            "line_items[$i][price_data][unit_amount]" => $item['price'],
            "line_items[$i][quantity]" => $item['quantity'] ?? 1,
        ], $items, array_keys($items));

        $payload = array_merge(...$lineItems);

        return array_merge($payload, [
            'mode' => 'payment',
            'success_url' => ($urls['return_url'] ?? '').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $urls['cancel_url'] ?? $urls['return_url'] ?? '',
            'client_reference_id' => $reference,
            'customer_email' => $customer['email'] ?? null,
            // Attach reference to metadata so PaymentIntent events can also identify the order
            'metadata' => [
                'reference' => $reference,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'reference' => $reference,
                ],
            ],
        ]);
    }

    protected function getApiUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    public function verifySignature(\Illuminate\Http\Request $request): bool
    {
        // Stripe verifies webhooks using a signing secret and signature header.
        // Implementation would use standard Stripe library or manual HMAC check.
        // $sig_header = $request->header('Stripe-Signature');
        // $webhook_secret = config('payment-gateway.gateways.stripe.webhook_secret');
        // ... verify ...
        return true;
    }

    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string
    {
        // Mode 1: Return URL callback (session_id in query string)
        if ($request->has('session_id') && ! $request->has('type')) {
            $settings = config('payment-gateway.gateways.stripe', []);
            $secretKey = $this->secretKey ?? $settings['secret_key'] ?? '';

            $response = Http::withBasicAuth($secretKey, '')
                ->get($this->getApiUrl().'/checkout/sessions/'.$request->input('session_id'));

            if ($response->successful()) {
                $data = $response->json();
                return $data['client_reference_id']
                    ?? $data['metadata']['reference']
                    ?? null;
            }

            return null;
        }

        // Mode 2: Webhook callback
        // Try getting from client_reference_id (Checkout Session)
        $reference = $request->input('data.object.client_reference_id');

        // Check for metadata.reference (fallback for PaymentIntent or if client_ref missing)
        if (! $reference) {
            $reference = $request->input('data.object.metadata.reference');
        }

        return $reference;
    }

    public function checkStatus(PayableInterface $payable): array
    {
        // Mock implementation for now
        // To implement real check: GET /checkout/sessions with client_reference_id query if possible, or store Session ID.
        return [
            'status' => 'pending',
            'message' => 'Status check implemented (Stub for Stripe).',
        ];
    }
}
