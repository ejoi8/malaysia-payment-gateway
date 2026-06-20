<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Feature;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestEloquentPayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

class PostPaymentRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestEloquentPayable::resetRegistry();
        config(['payment-gateway.model' => TestEloquentPayable::class]);
    }

    protected function tearDown(): void
    {
        TestEloquentPayable::resetRegistry();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function registerPaid(string $reference, array $extra = []): void
    {
        TestEloquentPayable::register(new TestEloquentPayable(array_merge([
            'reference' => $reference,
            'status' => PaymentStatus::defaultSuccessStatus(),
            'gateway' => 'chip',
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Redirect test',
        ], $extra)));
    }

    protected function getReturn(string $reference): TestResponse
    {
        // A CHIP (webhook) GET return for an already-verified payment.
        return $this->get(route('payment-gateway.webhook', ['driver' => 'chip']).'?reference='.$reference);
    }

    public function test_success_get_redirects_to_configured_url_with_reference(): void
    {
        config(['payment-gateway.redirects.success' => 'https://shop.test/thank-you']);
        $this->registerPaid('ok-1');

        $this->getReturn('ok-1')->assertRedirect('https://shop.test/thank-you?reference=ok-1');
    }

    public function test_success_get_substitutes_reference_placeholder(): void
    {
        config(['payment-gateway.redirects.success' => 'https://shop.test/orders/{reference}/done']);
        $this->registerPaid('ok-2');

        $this->getReturn('ok-2')->assertRedirect('https://shop.test/orders/ok-2/done');
    }

    public function test_success_get_appends_with_ampersand_when_url_already_has_query(): void
    {
        config(['payment-gateway.redirects.success' => 'https://shop.test/done?utm=x']);
        $this->registerPaid('ok-3');

        $this->getReturn('ok-3')->assertRedirect('https://shop.test/done?utm=x&reference=ok-3');
    }

    public function test_per_payment_override_wins_over_global_config(): void
    {
        config(['payment-gateway.redirects.success' => 'https://shop.test/global']);
        $this->registerPaid('ok-4', ['urls' => ['success_redirect' => 'https://booking.test/done']]);

        $this->getReturn('ok-4')->assertRedirect('https://booking.test/done?reference=ok-4');
    }

    public function test_falls_back_to_status_page_when_nothing_configured(): void
    {
        config(['payment-gateway.redirects.success' => null]);
        $this->registerPaid('ok-5');

        $this->getReturn('ok-5')->assertRedirect(route('payment-gateway.status', ['reference' => 'ok-5']));
    }

    public function test_failed_get_redirects_to_configured_failed_url(): void
    {
        config(['payment-gateway.redirects.failed' => 'https://shop.test/failed']);

        // Stripe is an API gateway: the GET return triggers verification, which
        // we fake as unpaid so the payment fails.
        Http::fake([
            '*checkout/sessions*' => Http::response([
                'id' => 'cs_x',
                'client_reference_id' => 'fail-1',
                'payment_status' => 'unpaid',
            ], 200),
        ]);

        TestEloquentPayable::register(new TestEloquentPayable([
            'reference' => 'fail-1',
            'status' => PaymentStatus::defaultPendingStatus(),
            'gateway' => 'stripe',
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Failing payment',
        ]));

        $this->get(route('payment-gateway.webhook', ['driver' => 'stripe']).'?session_id=cs_x')
            ->assertRedirect('https://shop.test/failed?reference=fail-1');
    }

    public function test_post_webhook_failure_is_acknowledged_with_200_to_stop_retries(): void
    {
        TestEloquentPayable::register(new TestEloquentPayable([
            'reference' => 'fail-2',
            'status' => PaymentStatus::defaultPendingStatus(),
            'gateway' => 'chip',
            'amount' => 1000,
            'currency' => 'MYR',
            'description' => 'Failing payment',
            'created_at' => Carbon::now(),
        ]));

        // A recorded failure is acknowledged (200, success:false) — NOT a 4xx,
        // which would make the gateway retry the failed-payment callback.
        $this->postJson(route('payment-gateway.webhook', ['driver' => 'chip']), [
            'reference' => 'fail-2',
            'status' => 'failed',
            'error' => 'Card declined',
        ])->assertOk()->assertJson(['success' => false, 'message' => 'Card declined']);
    }
}
