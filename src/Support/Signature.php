<?php

namespace Ejoi8\MalaysiaPaymentGateway\Support;

/**
 * Reusable signing/verification helpers shared by gateways.
 *
 * Payment gateways protect callbacks (and sometimes outgoing requests) with one
 * of a few recurring schemes: a keyed HMAC hash, a plain concatenated hash
 * (md5/sha512), or an asymmetric RSA signature. Centralising them here keeps
 * each gateway's verifySignature()/payload-signing small and consistent instead
 * of re-implementing crypto in every driver.
 */
class Signature
{
    /**
     * Keyed HMAC of a payload (hex). Used by Stripe webhooks and many others.
     */
    public static function hmac(string $payload, string $secret, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $payload, $secret);
    }

    /**
     * Plain hash of an already-concatenated string (hex).
     *
     * Used by gateways that sign by hashing concatenated fields, e.g. ToyyibPay
     * (`md5`), Midtrans (`sha512`), senangPay (`sha256`/`md5`).
     */
    public static function hash(string $value, string $algo = 'sha256'): string
    {
        return hash($algo, $value);
    }

    /**
     * Constant-time string comparison (timing-attack safe).
     */
    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * Verify an asymmetric RSA signature.
     *
     * Example — CHIP webhooks send a base64-encoded RSA signature of the SHA256
     * digest of the raw body in the `X-Signature` header:
     *
     *     Signature::rsaVerify(
     *         $request->getContent(),                     // exact raw body bytes
     *         base64_decode($request->header('X-Signature')),
     *         $publicKeyPem,
     *     );
     *
     * @param  string  $data          The exact signed data (e.g. raw request body).
     * @param  string  $signature     The decoded binary signature.
     * @param  string  $publicKeyPem  PEM-encoded RSA public key.
     */
    public static function rsaVerify(string $data, string $signature, string $publicKeyPem, int $algorithm = OPENSSL_ALGO_SHA256): bool
    {
        if ($publicKeyPem === '' || $signature === '') {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);

        if ($key === false) {
            return false;
        }

        return openssl_verify($data, $signature, $key, $algorithm) === 1;
    }
}
