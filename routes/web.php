<?php

use Illuminate\Support\Facades\Route;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\PaymentSandboxController;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\PaymentWebhookController;
use Ejoi8\MalaysiaPaymentGateway\Http\Controllers\PaymentStatusController;

// 1. Sandbox Routes
if (config('payment-gateway.sandbox.enabled', false)) {
    Route::group([
        'prefix'     => config('payment-gateway.sandbox.prefix', 'payment-gateway'),
        'middleware' => config('payment-gateway.sandbox.middleware', ['web']),
    ], function () {
        Route::get('/sandbox', [PaymentSandboxController::class, 'index'])
            ->name('payment-gateway.sandbox');
        Route::post('/sandbox', [PaymentSandboxController::class, 'initiate'])
            ->name('payment-gateway.sandbox.initiate');
    });
}

// 2. Main Payment Routes
$config = config('payment-gateway.routes', []);
$prefix = $config['prefix'] ?? 'payment';

// Webhook/Callback Route (Supports both POST webhooks and GET return URLs)
// POST: Webhooks from payment gateways (CHIP, ToyyibPay, Stripe, PayPal)
// GET: Return URLs from payment gateways (Stripe session_id, PayPal token)
Route::match(['get', 'post'], "/{$prefix}/webhook/{driver}", [PaymentWebhookController::class, 'handle'])
    ->name('payment-gateway.webhook')
    ->middleware('api');

// Status Pages (Defaulting to 'web' middleware)
Route::group([
    'prefix'     => $prefix,
    'middleware' => $config['middleware'] ?? ['web'],
], function () {
    // Specific payment status page
    Route::get('/status/{reference}', [PaymentStatusController::class, 'show'])
        ->name('payment-gateway.status');

    // Status Portal (Search/Index of payments)
    if (config('payment-gateway.status_portal.enabled', true)) {
        $path = config('payment-gateway.status_portal.path', 'check-status');

        Route::get("/{$path}", [PaymentStatusController::class, 'index'])
            ->name('payment-gateway.status.portal');

        Route::get("/{$path}/search", [PaymentStatusController::class, 'search'])
            ->name('payment-gateway.status.search');
    }
});
