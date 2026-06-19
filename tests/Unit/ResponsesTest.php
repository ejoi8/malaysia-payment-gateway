<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use LogicException;

class ResponsesTest extends TestCase
{
    public function test_redirect_response_exposes_typed_and_array_access(): void
    {
        $response = PaymentResponse::redirect(
            url: 'https://pay.test/123',
            transactionId: 'txn_1',
            payload: ['ref' => 'abc'],
            response: ['id' => 'txn_1'],
            extra: ['session_id' => 'cs_1', 'order_id' => 'ord_1'],
        );

        // Typed accessors (the new API).
        $this->assertTrue($response->isRedirect());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('https://pay.test/123', $response->redirectUrl());
        $this->assertSame('txn_1', $response->transactionId);

        // Array access (backward compatibility), including gateway-specific extras.
        $this->assertSame('redirect', $response['type']);
        $this->assertSame('https://pay.test/123', $response['url']);
        $this->assertSame('txn_1', $response['transaction_id']);
        $this->assertSame('cs_1', $response['session_id']);
        $this->assertSame('ord_1', $response['order_id']);
        $this->assertSame(['ref' => 'abc'], $response['payload']);
        $this->assertSame(['id' => 'txn_1'], $response['response']);
        $this->assertArrayHasKey('session_id', $response);
        $this->assertFalse(isset($response['missing']));
    }

    public function test_instructions_response_keeps_top_level_fields(): void
    {
        $fields = [
            'message' => 'Pay to 123',
            'bank_info' => 'Maybank 123',
            'reference' => 'ref-1',
            'amount' => 10000,
            'currency' => 'MYR',
        ];

        $response = PaymentResponse::instructions($fields, 'manual-ref-1', $fields);

        $this->assertTrue($response->isInstructions());
        $this->assertSame('instructions', $response['type']);
        $this->assertSame('Pay to 123', $response['message']);
        $this->assertSame('Maybank 123', $response['bank_info']);
        $this->assertSame(10000, $response['amount']);
        $this->assertSame('manual-ref-1', $response['transaction_id']);
        $this->assertSame('ref-1', $response['payload']['reference']);
    }

    public function test_error_response(): void
    {
        $response = PaymentResponse::error('boom', ['p' => 1], ['raw' => 2]);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isError());
        $this->assertSame('error', $response['type']);
        $this->assertSame('boom', $response['error']);
        $this->assertSame('boom', $response->errorMessage());
        $this->assertNull($response['transaction_id']);
    }

    public function test_response_is_json_serializable(): void
    {
        $response = PaymentResponse::redirect('https://x', 'txn', [], []);

        $this->assertSame($response->toArray(), json_decode(json_encode($response), true));
    }

    public function test_response_is_immutable(): void
    {
        $this->expectException(LogicException::class);

        $response = PaymentResponse::error('x');
        $response['type'] = 'redirect';
    }

    public function test_verification_result_success_and_failure(): void
    {
        $ok = VerificationResult::success('txn_9', ['k' => 'v']);

        $this->assertTrue($ok->success);
        $this->assertTrue($ok['success']);
        $this->assertSame('txn_9', $ok['transaction_id']);
        $this->assertSame(['k' => 'v'], $ok['meta']);

        $bad = VerificationResult::failure('declined', ['code' => 1]);

        $this->assertFalse($bad->success);
        $this->assertFalse($bad['success']);
        $this->assertSame('declined', $bad['error']);
        $this->assertNull($bad['transaction_id']);
    }

    public function test_form_post_response(): void
    {
        $response = PaymentResponse::formPost(
            'https://gw.test/pay',
            ['order_id' => 'o1', 'signature' => 'sig'],
            'o1',
            ['p' => 1],
        );

        $this->assertTrue($response->isFormPost());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('https://gw.test/pay', $response->formAction());
        $this->assertSame(['order_id' => 'o1', 'signature' => 'sig'], $response->formFields());
        $this->assertSame('POST', $response->formMethod());

        // array access
        $this->assertSame('form', $response['type']);
        $this->assertSame('https://gw.test/pay', $response['url']);
        $this->assertSame('sig', $response['fields']['signature']);
        $this->assertSame('o1', $response['transaction_id']);
    }

    public function test_client_token_response(): void
    {
        $response = PaymentResponse::clientToken('snap-token-123', 'order-1', ['client_key' => 'ck']);

        $this->assertTrue($response->isClientToken());
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('snap-token-123', $response->token());
        $this->assertSame('client_token', $response['type']);
        $this->assertSame('snap-token-123', $response['token']);
        $this->assertSame(['client_key' => 'ck'], $response['client']);
    }
}
