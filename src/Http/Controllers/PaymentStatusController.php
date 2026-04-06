<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\Concerns\ResolvesPayables;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

class PaymentStatusController extends Controller
{
    use ResolvesPayables;

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

        try {
            $payable = $this->findPayable($reference);

            if (! $payable) {
                return back()->with('error', 'Payment reference not found.');
            }

            return redirect()->route('payment-gateway.status', [
                'reference' => $payable->getPaymentReference(),
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the status of a payment.
     */
    public function show(string $reference, GatewayManager $manager)
    {
        try {
            $payable = $this->findPayable($reference);
        } catch (RuntimeException $e) {
            abort(500, $e->getMessage());
        }

        if (! $payable) {
            abort(404, 'Payment not found');
        }

        $driver = $this->resolvePayableDriver($payable);
        $actualStatus = $payable->status ?? PaymentStatus::UNKNOWN->value;

        $statusInfo = [
            'status' => $actualStatus,
            'message' => PaymentStatus::getMessage($actualStatus),
        ];

        if (PaymentStatus::isPending($actualStatus)) {
            try {
                $apiCheck = $manager->checkStatus($driver, $payable);

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
