<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Base class for all payment gateways.
 *
 * Provides the machinery shared by every gateway — config resolution, the
 * factory method, URL helpers, response builders and sensible defaults for
 * optional capabilities — so a concrete gateway only implements what is unique
 * to it: getName(), getType(), initiate(), verify() and
 * getPaymentIdFromRequest(). Override a capability method (e.g. refund(),
 * verifySignature(), checkStatus()) only when the gateway actually supports it.
 */
abstract class AbstractGateway implements GatewayInterface
{
    /**
     * @param  array<string, mixed>  $config  The gateway's configuration block.
     */
    public function __construct(protected array $config = []) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): static
    {
        return new static($config);
    }

    abstract public function getName(): string;

    abstract public function getType(): GatewayType;

    abstract public function initiate(PayableInterface $payable): PaymentResponse;

    /**
     * @param  array<string, mixed>  $payload
     */
    abstract public function verify(PayableInterface $payable, array $payload): VerificationResult;

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function refund(string $transactionId, ?int $amount = null): VerificationResult
    {
        return VerificationResult::failure(
            sprintf('The %s gateway does not support refunds.', $this->getName())
        );
    }

    public function verifySignature(Request $request): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        return $this->statusResult(PaymentStatus::PENDING, 'Status check is not supported for this gateway.');
    }

    /**
     * Resolve the stored gateway transaction id for status queries.
     *
     * Uses the optional getPaymentTransactionId() hook if present, otherwise the
     * model's transaction_id attribute. Persisted at initiation (see
     * persist_initiation_id), so it's available for pending payments.
     */
    protected function transactionId(PayableInterface $payable): ?string
    {
        if (method_exists($payable, 'getPaymentTransactionId')) {
            return $payable->getPaymentTransactionId();
        }

        if ($payable instanceof Model) {
            return $payable->getAttribute('transaction_id');
        }

        return $payable->transaction_id ?? null;
    }

    /**
     * Normalized checkStatus() result.
     *
     * @return array<string, mixed>
     */
    protected function statusResult(PaymentStatus $status, string $message, ?string $transactionId = null): array
    {
        return [
            'status' => $status->value,
            'message' => $message,
            'transaction_id' => $transactionId,
        ];
    }

    /**
     * Resolve a configuration value.
     *
     * Resolution order: per-call payable settings → the gateway's own config
     * array → the published config file (keyed by gateway name) → $default.
     * Null and empty-string values are skipped so they never mask a usable
     * fallback.
     *
     * @param  array<string, mixed>  $settings  Per-call overrides from the payable.
     */
    protected function setting(string $key, mixed $default = null, array $settings = []): mixed
    {
        foreach ([$settings, $this->config] as $bag) {
            if (array_key_exists($key, $bag) && $bag[$key] !== null && $bag[$key] !== '') {
                return $bag[$key];
            }
        }

        $global = config("payment-gateway.gateways.{$this->getName()}.{$key}");

        return ($global !== null && $global !== '') ? $global : $default;
    }

    /**
     * A pre-configured HTTP client with sensible timeouts.
     *
     * Gateways should make outbound calls through this (e.g.
     * `$this->http()->withToken($key)->post(...)`) so every driver shares the
     * same timeout/error behaviour. No automatic retry is applied — payment
     * creation is not idempotent and must not be silently retried.
     */
    protected function http(): PendingRequest
    {
        return Http::timeout((int) config('payment-gateway.http.timeout', 30))
            ->connectTimeout((int) config('payment-gateway.http.connect_timeout', 10));
    }

    /**
     * Append the payment reference to a redirect/return URL as a query
     * parameter, so GET returns can identify the payment.
     */
    protected function appendReference(string $url, string $reference): string
    {
        if ($url === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'reference='.$reference;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $extra
     */
    protected function redirect(string $url, ?string $transactionId, array $payload = [], array $response = [], array $extra = []): PaymentResponse
    {
        return PaymentResponse::redirect($url, $transactionId, $payload, $response, $extra);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $payload
     */
    protected function form(string $action, array $fields, ?string $transactionId, array $payload = [], string $method = 'POST'): PaymentResponse
    {
        return PaymentResponse::formPost($action, $fields, $transactionId, $payload, $method);
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>  $payload
     */
    protected function clientToken(string $token, ?string $transactionId, array $client = [], array $payload = []): PaymentResponse
    {
        return PaymentResponse::clientToken($token, $transactionId, $client, $payload);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $payload
     */
    protected function instructions(array $fields, ?string $transactionId, array $payload = []): PaymentResponse
    {
        return PaymentResponse::instructions($fields, $transactionId, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $extra
     */
    protected function fail(string $error, array $payload = [], array $response = [], array $extra = []): PaymentResponse
    {
        return PaymentResponse::error($error, $payload, $response, $extra);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function verified(?string $transactionId, array $meta = []): VerificationResult
    {
        return VerificationResult::success($transactionId, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function rejected(string $error, array $meta = []): VerificationResult
    {
        return VerificationResult::failure($error, $meta);
    }
}
