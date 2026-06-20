<?php

namespace Ejoi8\MalaysiaPaymentGateway\Responses;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;

/**
 * Normalized result of a gateway's verify() or refund() call.
 *
 * New code should prefer the typed properties ($result->success,
 * $result->transactionId, $result->error, $result->meta). Array access —
 * e.g. $result['success'], $result['transaction_id'] — is preserved for
 * backward compatibility with v1 consumers and tests.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
final class VerificationResult implements ArrayAccess, Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $error = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(?string $transactionId, array $meta = []): self
    {
        return new self(true, $transactionId, null, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function failure(string $error, array $meta = []): self
    {
        return new self(false, null, $error, $meta);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'error' => $this->error,
            'meta' => $this->meta,
        ];
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
        throw new LogicException('VerificationResult is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('VerificationResult is immutable.');
    }
}
