<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\Concerns\ResolvesPayables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
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

            $payload = $request->all();
            $result = $manager->verify($driver, $payable, $payload);

            Log::info("Callback processed for {$reference}: ".($result['success'] ? 'Success' : 'Failed'));

            if ($result['success']) {
                return $this->successResponse($request, $payable, 'Payment successful');
            }

            return $this->errorResponse($request, $result['error'] ?? 'Payment verification failed', 400);

        } catch (\Exception $e) {
            Log::error('Callback exception: '.$e->getMessage());

            return $this->errorResponse($request, 'Server Error', 500);
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
            // Redirect user to payment status page
            $statusUrl = route('payment-gateway.status', ['reference' => $payable->getPaymentReference()]);

            return redirect($statusUrl)->with('success', $message);
        }

        // JSON response for webhooks
        return response()->json(['success' => true, 'message' => $message]);
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
