<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Closure;
use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\Concerns\ResolvesPayables;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentWebhookController extends Controller
{
    use ResolvesPayables;

    /**
     * Handle the incoming webhook or return URL callback.
     *
     * This unified endpoint handles both:
     * - POST: Webhooks from payment gateways (CHIP, ToyyibPay, Stripe, PayPal)
     * - GET: Return URLs from payment gateways (Stripe session_id, PayPal token)
     */
    public function handle(Request $request, string $driver, GatewayManager $manager)
    {
        Log::info('Payment callback received', [
            'driver' => $driver,
            'method' => $request->method(),
        ]);

        try {
            $gateway = $manager->driver($driver);

            if ($request->isMethod('POST') && ! $gateway->verifySignature($request)) {
                Log::warning("Webhook signature verification failed for {$driver}");

                return response()->json(['message' => 'Invalid signature'], 403);
            }

            $reference = $gateway->getPaymentIdFromRequest($request);

            if (! $reference) {
                Log::error("Callback error: Could not extract payment reference from request for {$driver}");

                return $this->errorResponse($request, 'Payment reference not found in payload', 400);
            }

            // Serialize concurrent callbacks for the same payment so two
            // simultaneous webhooks can't both verify-and-process it.
            return $this->withCallbackLock(
                $reference,
                fn () => $this->process($request, $driver, $reference, $manager, $gateway),
            );

        } catch (\Exception $e) {
            Log::error('Callback exception: '.$e->getMessage());

            return $this->errorResponse($request, 'Server Error', 500);
        }
    }

    /**
     * Resolve the payable and process the callback.
     *
     * Runs inside the per-reference lock and re-reads the payment's current
     * state, so a duplicate callback that waited on the lock sees the
     * already-processed status and short-circuits.
     */
    protected function process(Request $request, string $driver, string $reference, GatewayManager $manager, GatewayInterface $gateway)
    {
        try {
            $payable = $this->findPayable($reference);
        } catch (RuntimeException $e) {
            Log::error('Callback error: '.$e->getMessage());

            return $this->errorResponse($request, 'Server Configuration Error', 500);
        }

        if (! $payable) {
            Log::error("Callback error: Payment record not found for reference {$reference}");

            return $this->errorResponse($request, 'Payment record not found', 404);
        }

        $currentStatus = $payable->status ?? PaymentStatus::UNKNOWN->value;
        if (PaymentStatus::isSuccess($currentStatus)) {
            Log::info("Payment {$reference} is already processed (Status: {$currentStatus})");

            return $this->successResponse($request, $payable, 'Payment successful');
        }

        if ($this->shouldIgnoreStaleCallback($request, $payable)) {
            $message = 'Callback ignored because it arrived after the allowed processing window.';

            Log::warning('Ignoring stale payment callback', [
                'driver' => $driver,
                'reference' => $reference,
                'max_age_minutes' => $this->callbackMaxAgeMinutes(),
                'created_at' => $this->resolvePayableCreatedAt($payable)?->toIso8601String(),
            ]);

            return $this->ignoredResponse($request, $payable, $message);
        }

        if ($request->isMethod('GET') && ! $gateway->getType()->requiresGetVerification()) {
            Log::info("GET return for {$gateway->getType()->value}-based gateway {$driver}, redirecting to status page");

            return $this->redirectToStatus($payable);
        }

        $result = $manager->verify($driver, $payable, $request->all());

        Log::info("Callback processed for {$reference}: ".($result['success'] ? 'Success' : 'Failed'));

        if ($result['success']) {
            return $this->successResponse($request, $payable, 'Payment successful');
        }

        return $this->failureResponse($request, $payable, $result['error'] ?? 'Payment verification failed');
    }

    /**
     * Run $callback while holding an atomic per-reference lock so concurrent
     * callbacks for the same payment are serialized. Degrades gracefully when
     * the cache store has no lock support or the lock can't be acquired in time.
     */
    protected function withCallbackLock(string $reference, Closure $callback)
    {
        if (! config('payment-gateway.callbacks.lock', true)) {
            return $callback();
        }

        try {
            return Cache::lock('mpg-callback:'.$reference, (int) config('payment-gateway.callbacks.lock_seconds', 10))
                ->block((int) config('payment-gateway.callbacks.lock_wait', 5), $callback);
        } catch (LockTimeoutException $e) {
            Log::warning('Payment callback lock contention; processing without lock', ['reference' => $reference]);

            return $callback();
        } catch (\Throwable $e) {
            Log::debug('Payment callback locking unavailable; processing without lock', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    /**
     * Redirect user to payment status page.
     */
    protected function redirectToStatus(PayableInterface $payable)
    {
        $statusUrl = route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]);

        return redirect($statusUrl);
    }

    /**
     * Return success response based on request type.
     *
     * For GET requests (return URLs): redirect to status page
     * For POST requests (webhooks): return JSON response
     */
    protected function successResponse(Request $request, PayableInterface $payable, string $message)
    {
        if ($request->isMethod('GET')) {
            return redirect($this->resolveRedirect($payable, 'success'));
        }

        // JSON response for webhooks
        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * Return a failed-payment response.
     *
     * For GET returns: redirect to the configured failed URL (or the status page).
     * For POST webhooks: acknowledge with HTTP 200 (the failure has been recorded)
     * so the gateway stops retrying — a non-2xx would trigger retries, duplicate
     * failure notifications, and can get the endpoint disabled by the gateway.
     * The body reports the failed outcome for anyone inspecting the response.
     */
    protected function failureResponse(Request $request, PayableInterface $payable, string $message)
    {
        if ($request->isMethod('GET')) {
            return redirect($this->resolveRedirect($payable, 'failed'));
        }

        return response()->json(['success' => false, 'message' => $message]);
    }

    /**
     * Resolve where to send the customer after payment.
     *
     * Precedence: per-payment override (metadata.urls.{type}_redirect) →
     * global config (redirects.{type}) → the built-in status page.
     *
     * @param  string  $type  'success' or 'failed'
     */
    protected function resolveRedirect(PayableInterface $payable, string $type): string
    {
        $custom = $payable->getPaymentUrls()["{$type}_redirect"] ?? null;
        $custom = $custom ?: config("payment-gateway.redirects.{$type}");

        if ($custom) {
            return $this->withReference((string) $custom, $payable->getPaymentReference());
        }

        return route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]);
    }

    /**
     * Add the payment reference to a redirect URL — substituting a {reference}
     * placeholder if present, otherwise appending it as a query parameter.
     */
    protected function withReference(string $url, string $reference): string
    {
        if (str_contains($url, '{reference}')) {
            return str_replace('{reference}', rawurlencode($reference), $url);
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'reference='.rawurlencode($reference);
    }

    /**
     * Return ignored response based on request type.
     */
    protected function ignoredResponse(Request $request, PayableInterface $payable, string $message)
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToStatus($payable)->with('warning', $message);
        }

        return response()->json([
            'success' => true,
            'ignored' => true,
            'message' => $message,
        ]);
    }

    /**
     * Return error response based on request type.
     *
     * For GET requests (return URLs): redirect with error
     * For POST requests (webhooks): return JSON error
     */
    protected function errorResponse(Request $request, string $message, int $status = 400)
    {
        if ($request->isMethod('GET')) {
            // Redirect user with error message
            // If we have a payable reference, go to status page
            // Otherwise, redirect to status portal or home
            $portalEnabled = config('payment-gateway.status_portal.enabled', true);
            if ($portalEnabled) {
                return redirect()->route('payment-gateway.status.portal')
                    ->with('error', $message);
            }

            return redirect('/')->with('error', $message);
        }

        // JSON response for webhooks
        return response()->json(['message' => $message], $status);
    }

    protected function shouldIgnoreStaleCallback(Request $request, PayableInterface $payable): bool
    {
        if (! $request->isMethod('POST')) {
            return false;
        }

        $maxAgeMinutes = $this->callbackMaxAgeMinutes();

        if ($maxAgeMinutes <= 0) {
            return false;
        }

        $createdAt = $this->resolvePayableCreatedAt($payable);

        if (! $createdAt) {
            return false;
        }

        return now()->greaterThan($createdAt->copy()->addMinutes($maxAgeMinutes));
    }

    protected function callbackMaxAgeMinutes(): int
    {
        return max(0, (int) config('payment-gateway.callbacks.max_age_minutes', 10));
    }

    protected function resolvePayableCreatedAt(PayableInterface $payable): ?Carbon
    {
        $createdAt = null;

        if ($payable instanceof Model) {
            $createdAt = $payable->getAttribute($payable->getCreatedAtColumn());
        } elseif (property_exists($payable, 'created_at')) {
            $createdAt = $payable->created_at;
        }

        if ($createdAt instanceof Carbon) {
            return $createdAt;
        }

        if ($createdAt instanceof \DateTimeInterface) {
            return Carbon::instance($createdAt);
        }

        if (is_string($createdAt) && $createdAt !== '') {
            try {
                return Carbon::parse($createdAt);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
