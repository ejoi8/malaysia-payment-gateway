<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Ejoi8\MalaysiaPaymentGateway\Support\Signature;
use Illuminate\Http\Request;

/**
 * ToyyibPay payment gateway (Malaysian FPX provider).
 *
 * @see https://toyyibpay.com/apireference
 */
class ToyyibPayGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'toyyibpay';
    }

    public function getType(): GatewayType
    {
        return GatewayType::WEBHOOK;
    }

    public function initiate(PayableInterface $payable): PaymentResponse
    {
        $payload = $this->buildPayload($payable);

        $response = $this->http()->asForm()->post($this->getApiUrl().'index.php/api/createBill', $payload);

        if ($response->failed()) {
            return $this->fail(
                'ToyyibPay API Error: '.$response->body(),
                $payload,
                $response->json() ?: ['body' => $response->body()],
            );
        }

        // ToyyibPay returns success as [{"BillCode":"..."}].
        $data = $response->json();
        $billCode = $data[0]['BillCode'] ?? null;

        if (! $billCode) {
            return $this->fail(
                'ToyyibPay did not return a BillCode: '.$response->body(),
                $payload,
                $data ?: ['body' => $response->body()],
            );
        }

        return $this->redirect($this->getCheckoutUrl($billCode), $billCode, $payload, $data);
    }

    /**
     * Verify a ToyyibPay callback/return.
     *
     * ToyyibPay sends an integer status: 1 = success, 2 = pending, 3 = fail.
     * The return URL uses 'status_id' while the callback uses 'status'.
     */
    public function verify(PayableInterface $payable, array $payload): VerificationResult
    {
        $status = (int) ($payload['status'] ?? $payload['status_id'] ?? 0);

        if ($status === 1) {
            return $this->verified(
                $payload['transaction_id'] ?? $payload['refno'] ?? $payload['billcode'] ?? null,
                $payload,
            );
        }

        return $this->rejected($payload['reason'] ?? $payload['msg'] ?? 'Payment not successful', $payload);
    }

    /**
     * Verify the authenticity of a ToyyibPay callback.
     *
     * The server-to-server callback POSTs a `hash` field equal to
     * md5(userSecretKey + status + order_id + refno + "ok"). The browser return
     * (GET) carries no hash, so there is nothing to verify there.
     */
    public function verifySignature(Request $request): bool
    {
        $hash = $request->input('hash');

        if (! $hash) {
            // GET return / payloads without a hash — nothing to verify here.
            return true;
        }

        $expected = Signature::hash(
            (string) $this->setting('secret_key', '')
                .$request->input('status')
                .$request->input('order_id')
                .$request->input('refno')
                .'ok',
            'md5',
        );

        return Signature::equals($expected, (string) $hash);
    }

    public function getPaymentIdFromRequest(Request $request): ?string
    {
        // 'order_id' is our external reference; 'refno' is ToyyibPay's internal ID.
        return $request->input('order_id') ?? $request->input('billcode') ?? $request->input('refno');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $id = $this->transactionId($payable); // the bill code

        if (! $id) {
            return $this->statusResult(PaymentStatus::PENDING, 'No ToyyibPay bill code stored yet.');
        }

        $response = $this->http()->asForm()->post($this->getApiUrl().'index.php/api/getBillTransactions', [
            'userSecretKey' => $this->setting('secret_key', '', $settings),
            'billCode' => $id,
        ]);

        if ($response->failed()) {
            return $this->statusResult(PaymentStatus::PENDING, 'Could not retrieve status from ToyyibPay.', $id);
        }

        // billpaymentStatus: 1 = success, 2 = pending, 3 = fail.
        $payStatus = (string) ($response->json()[0]['billpaymentStatus'] ?? '');

        return match ($payStatus) {
            '1' => $this->statusResult(PaymentStatus::PAID, 'Payment confirmed by ToyyibPay.', $id),
            '3' => $this->statusResult(PaymentStatus::FAILED, 'Payment failed.', $id),
            default => $this->statusResult(PaymentStatus::PENDING, 'Payment still pending.', $id),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();

        // ToyyibPay charges a single bill at billAmount; it has no line items.
        return [
            'userSecretKey' => $this->setting('secret_key', '', $settings),
            'categoryCode' => $this->setting('category_code', '', $settings),
            'billName' => $payable->getPaymentReference(),
            'billDescription' => $payable->getPaymentDescription(),
            'billPriceSetting' => 0,
            'billPayorInfo' => 1,
            'billAmount' => $payable->getPaymentAmount(),
            'billReturnUrl' => $urls['return_url'] ?? '',
            'billCallbackUrl' => $urls['callback_url'] ?? '',
            'billExternalReferenceNo' => $payable->getPaymentReference(),
            'billTo' => $customer['name'] ?? '',
            'billEmail' => $customer['email'] ?? '',
            'billPhone' => $customer['phone'] ?? '',
            'billSplitPayment' => 0,
            'billSplitPaymentArgs' => '',
            'billPaymentChannel' => '0',
            'billContentEmail' => 'Thank you for your payment.',
            'billChargeToCustomer' => $this->setting('charge_customer', 1, $settings),
            'billExpiryDate' => null,
            'billExpiryDays' => $this->setting('expiry_days', 3, $settings),
        ];
    }

    protected function getApiUrl(): string
    {
        return $this->isSandbox()
            ? 'https://dev.toyyibpay.com/'
            : 'https://toyyibpay.com/';
    }

    protected function getCheckoutUrl(string $billCode): string
    {
        $baseUrl = $this->isSandbox()
            ? 'https://dev.toyyibpay.com'
            : 'https://toyyibpay.com';

        return $baseUrl.'/'.$billCode;
    }

    protected function isSandbox(): bool
    {
        return (bool) $this->setting('sandbox', false);
    }
}
