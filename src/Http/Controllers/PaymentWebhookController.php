<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle the incoming webhook or return URL callback.
     *
     * This unified endpoint handles both:
     * - POST: Webhooks from payment gateways (CHIP, ToyyibPay, Stripe, PayPal)
     * - GET: Return URLs from payment gateways (Stripe session_id, PayPal token)
     */
    public function handle(Request $request, string $driver, GatewayManager $manager)
    {
        $method = $request->method();
        Log::info("Payment callback received for {$driver} [{$method}]", [
            'method' => $method,
            'payload' => $request->all(),
        ]);

        try {
            // 1. Resolve the gateway driver
            $gateway = $manager->driver($driver);

            // 2. Verify Signature (only for POST webhooks, skip for GET return URLs)
            if ($request->isMethod('POST') && ! $gateway->verifySignature($request)) {
                Log::warning("Webhook signature verification failed for {$driver}");

                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // 3. Extract Payment ID/Reference
            $reference = $gateway->getPaymentIdFromRequest($request);

            if (! $reference) {
                Log::error("Callback error: Could not extract payment reference from request for {$driver}");

                return $this->errorResponse($request, 'Payment reference not found in payload', 400);
            }

            // 4. Find the Payable Model
            $modelClass = config('payment-gateway.model');
            if (! $modelClass) {
                Log::error('Callback error: Payment model not configured.');

                return $this->errorResponse($request, 'Server Configuration Error', 500);
            }

            // Try resolving by reference first (most common)
            $payable = null;
            if (method_exists($modelClass, 'findByReference')) {
                $payable = $modelClass::findByReference($reference);
            }

            // If not found, maybe the reference IS the ID (less common but possible)
            if (! $payable) {
                try {
                    $payable = $modelClass::where('id', $reference)->first();
                } catch (\Exception $e) {
                }
            }

            if (! $payable) {
                Log::error("Callback error: Payment record not found for reference {$reference}");

                return $this->errorResponse($request, 'Payment record not found', 404);
            }

            // 5. Check if payment is already processed (idempotency)
            $currentStatus = $payable->status ?? null;
            if (PaymentStatus::isSuccess($currentStatus)) {
                Log::info("Payment {$reference} is already processed (Status: {$currentStatus})");

                return $this->successResponse($request, $payable, 'Payment successful');
            }

            // 6. Handle GET vs POST differently based on gateway type
            // For GET requests on webhook-based gateways, just redirect to status page
            // (the webhook already processed or will process the payment)
            // For GET on API-based gateways (Stripe, PayPal), we still need to verify
            if ($request->isMethod('GET') && ! $gateway->getType()->requiresGetVerification()) {
                // For webhook-based gateways (CHIP, ToyyibPay), the webhook handles verification
                // Just redirect user to status page - payment may still be pending or already processed
                Log::info("GET return for {$gateway->getType()->value}-based gateway {$driver}, redirecting to status page");

                return $this->redirectToStatus($payable);
            }

            // 7. Build payload for verification
            $payload = $request->all();

            // 8. Verify & Process Payment
            // This will trigger PaymentSucceeded or PaymentFailed events
            $result = $manager->verify($driver, $payable, $payload);

            Log::info("Callback processed for {$reference}: ".($result['success'] ? 'Success' : 'Failed'));

            // 9. Return appropriate response based on request type
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
    protected function redirectToStatus($payable)
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
    protected function successResponse(Request $request, $payable, string $message)
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
}
