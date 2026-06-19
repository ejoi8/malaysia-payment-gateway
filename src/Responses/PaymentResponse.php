<?php

namespace Ejoi8\MalaysiaPaymentGateway\Responses;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;

/**
 * Normalized result of a gateway's initiate() call.
 *
 * New code should prefer the typed accessors (isRedirect(), redirectUrl(),
 * errorMessage(), get()). Array access — e.g. $response['url'],
 * $response['session_id'], $response['bank_info'] — is preserved so existing
 * v1 consumers, Blade views and tests keep working unchanged.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
final class PaymentResponse implements ArrayAccess, Arrayable, JsonSerializable
{
    public const TYPE_REDIRECT = 'redirect';

    public const TYPE_FORM = 'form';

    public const TYPE_CLIENT_TOKEN = 'client_token';

    public const TYPE_INSTRUCTIONS = 'instructions';

    public const TYPE_ERROR = 'error';

    /**
     * @param  array<string, mixed>  $payload     Request payload sent to the gateway.
     * @param  array<string, mixed>  $attributes  Remaining top-level keys (url, response,
     *                                            error, plus gateway-specific extras such
     *                                            as session_id / order_id / bank_info).
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $transactionId,
        public readonly array $payload = [],
        private readonly array $attributes = [],
    ) {}

    /**
     * Redirect-based result (CHIP, ToyyibPay, Stripe, PayPal).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response  Raw provider response.
     * @param  array<string, mixed>  $extra     Gateway-specific keys (e.g. session_id, order_id).
     */
    public static function redirect(string $url, ?string $transactionId, array $payload = [], array $response = [], array $extra = []): self
    {
        return new self(self::TYPE_REDIRECT, $transactionId, $payload, array_merge([
            'url' => $url,
            'response' => $response,
        ], $extra));
    }

    /**
     * Auto-submitting form-POST result (e.g. iPay88, senangPay).
     *
     * The customer's browser must POST $fields (typically including a signature)
     * to $action. Render with the bundled `payment-gateway::auto-submit` view.
     *
     * @param  array<string, mixed>  $fields   Hidden form fields to submit.
     * @param  array<string, mixed>  $payload
     */
    public static function formPost(string $action, array $fields, ?string $transactionId, array $payload = [], string $method = 'POST'): self
    {
        return new self(self::TYPE_FORM, $transactionId, $payload, [
            'url' => $action,
            'fields' => $fields,
            'method' => $method,
        ]);
    }

    /**
     * Client-side token result for JS-SDK gateways (e.g. Midtrans Snap, Razorpay).
     *
     * @param  array<string, mixed>  $client   Extra data the front-end SDK needs (keys, order id, ...).
     * @param  array<string, mixed>  $payload
     */
    public static function clientToken(string $token, ?string $transactionId, array $client = [], array $payload = []): self
    {
        return new self(self::TYPE_CLIENT_TOKEN, $transactionId, $payload, array_merge(
            ['token' => $token],
            $client === [] ? [] : ['client' => $client],
        ));
    }

    /**
     * Instruction-based result (manual / offline payments).
     *
     * @param  array<string, mixed>  $fields   Top-level instruction fields (message, bank_info, ...).
     * @param  array<string, mixed>  $payload
     */
    public static function instructions(array $fields, ?string $transactionId, array $payload = []): self
    {
        return new self(self::TYPE_INSTRUCTIONS, $transactionId, $payload, $fields);
    }

    /**
     * Error result (initiation failed).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $error, array $payload = [], array $response = [], array $extra = []): self
    {
        return new self(self::TYPE_ERROR, null, $payload, array_merge([
            'error' => $error,
            'response' => $response,
        ], $extra));
    }

    public function isSuccessful(): bool
    {
        return $this->type !== self::TYPE_ERROR;
    }

    public function isRedirect(): bool
    {
        return $this->type === self::TYPE_REDIRECT;
    }

    public function isInstructions(): bool
    {
        return $this->type === self::TYPE_INSTRUCTIONS;
    }

    public function isFormPost(): bool
    {
        return $this->type === self::TYPE_FORM;
    }

    public function isClientToken(): bool
    {
        return $this->type === self::TYPE_CLIENT_TOKEN;
    }

    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    public function redirectUrl(): ?string
    {
        return $this->attributes['url'] ?? null;
    }

    public function formAction(): ?string
    {
        return $this->attributes['url'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formFields(): array
    {
        return $this->attributes['fields'] ?? [];
    }

    public function formMethod(): string
    {
        return $this->attributes['method'] ?? 'POST';
    }

    public function token(): ?string
    {
        return $this->attributes['token'] ?? null;
    }

    public function errorMessage(): ?string
    {
        return $this->attributes['error'] ?? null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->toArray();

        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->attributes, [
            'type' => $this->type,
            'payload' => $this->payload,
            'transaction_id' => $this->transactionId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('PaymentResponse is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('PaymentResponse is immutable.');
    }
}
