<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentStatusController extends Controller
{
    /**
     * Show the search form.
     */
    public function index()
    {
        if (! config('payment-gateway.status_portal.enabled', true)) {
            abort(404);
        }

        return view('payment-gateway::check-status');
    }

    /**
     * Process the search.
     */
    public function search(Request $request)
    {
        $request->validate(['reference' => 'required|string']);

        $reference = $request->input('reference');
        $modelClass = config('payment-gateway.model');

        if (! $modelClass) {
            return back()->with('error', 'System misconfiguration: Model not defined.');
        }

        try {
            // Use the findByReference method from interface
            $payable = null;
            if (method_exists($modelClass, 'findByReference')) {
                $payable = $modelClass::findByReference($reference);
            }

            if (! $payable) {
                // Fallback to searching by ID if reference lookup fails/not implemented
                try {
                    $payable = $modelClass::findOrFail($reference);
                } catch (\Exception $e) {
                    return back()->with('error', 'Payment reference not found.');
                }
            }

            if (! $payable) {
                return back()->with('error', 'Payment reference not found.');
            }

            // Determine driver:
            // 1. Try to get 'gateway' or 'payment_method' from the model itself
            $driver = $payable->gateway ?? $payable->payment_method ?? null;

            // 2. If not found on model, check request input
            if (! $driver) {
                $driver = $request->input('driver');
            }

            // 3. Fallback to default
            if (! $driver) {
                $driver = config('payment-gateway.default', 'chip');
            }

            return redirect()->route('payment-gateway.status', [
                'reference' => $reference,
            ]);

        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while searching: '.$e->getMessage());
        }
    }

    /**
     * Display the status of a payment.
     */
    public function show(string $reference, GatewayManager $manager)
    {
        $modelClass = config('payment-gateway.model');

        if (! $modelClass || ! class_exists($modelClass)) {
            abort(500, 'Payment Gateway: Model class not configured.');
        }

        // Try to resolve using findByReference if available, else fallback to findOrFail
        $payable = null;
        if (method_exists($modelClass, 'findByReference')) {
            $payable = $modelClass::findByReference($reference);
        }

        if (! $payable) {
            try {
                $payable = $modelClass::findOrFail($reference);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                abort(404, 'Order not found');
            }
        }

        // Determine driver from model or default
        $driver = $payable->gateway ?? $payable->payment_method ?? config('payment-gateway.default', 'chip');

        // Get the actual status from the payable model (updated by webhook)
        // This is more reliable than calling gateway API
        $actualStatus = $payable->status ?? PaymentStatus::UNKNOWN->value;

        // Build status info array using the PaymentStatus enum
        $statusInfo = [
            'status' => $actualStatus,
            'message' => PaymentStatus::getMessage($actualStatus),
        ];

        // Optionally, if status is still pending, try to check via gateway API
        // This provides real-time status for edge cases
        if (PaymentStatus::isPending($actualStatus)) {
            try {
                $apiCheck = $manager->checkStatus($driver, $payable);
                // Only override if API returns a more definitive status
                if (isset($apiCheck['status']) && ! PaymentStatus::isPending($apiCheck['status'])) {
                    $statusInfo = array_merge($statusInfo, $apiCheck);
                }
            } catch (\Exception $e) {
                // Ignore API errors, use local status
            }
        }

        return view('payment-gateway::status', [
            'payable' => $payable,
            'driver' => $driver,
            'check' => $statusInfo,
        ]);
    }
}
