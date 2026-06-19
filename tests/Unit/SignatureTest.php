<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Support\Signature;
use Ejoi8\MalaysiaPaymentGateway\Tests\RsaTestKey;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class SignatureTest extends TestCase
{
    public function test_hmac_matches_native(): void
    {
        $this->assertSame(hash_hmac('sha256', 'payload', 'secret'), Signature::hmac('payload', 'secret'));
        $this->assertSame(hash_hmac('sha512', 'payload', 'secret'), Signature::hmac('payload', 'secret', 'sha512'));
    }

    public function test_hash_matches_native(): void
    {
        $this->assertSame(md5('abc'), Signature::hash('abc', 'md5'));
        $this->assertSame(hash('sha256', 'abc'), Signature::hash('abc'));
    }

    public function test_equals_compares_correctly(): void
    {
        $this->assertTrue(Signature::equals('same', 'same'));
        $this->assertFalse(Signature::equals('a', 'b'));
    }

    public function test_rsa_verify_accepts_valid_and_rejects_tampered(): void
    {
        $publicPem = RsaTestKey::publicPem();
        $body = '{"event":"paid"}';
        $signature = base64_decode(RsaTestKey::SIG_EVENT_PAID);

        $this->assertTrue(Signature::rsaVerify($body, $signature, $publicPem));
        $this->assertFalse(Signature::rsaVerify('{"event":"tampered"}', $signature, $publicPem));
        $this->assertFalse(Signature::rsaVerify($body, $signature, 'not-a-pem-key'));
        $this->assertFalse(Signature::rsaVerify($body, '', $publicPem));
    }
}
