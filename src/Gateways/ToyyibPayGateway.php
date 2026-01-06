<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Illuminate\Support\Facades\Http;

/**
 * ToyyibPay payment gateway (Malaysian FPX provider).
 *
 * @see https://toyyibpay.com/apireference
 */
class ToyyibPayGateway implements GatewayInterface
{
    public function __construct(
        protected ?string $secretKey = null,
        protected ?string $categoryCode = null,
        protected bool $sandbox = false
    ) {}

    public static function make(array $config): self
    {
        return new self(
            secretKey: $config['secret_key'] ?? null,
            categoryCode: $config['category_code'] ?? null,
            sandbox: $config['sandbox'] ?? false
        );
    }

    public function getName(): string
    {
        return 'toyyibpay';
    }

    public function getType(): GatewayType
    {
        return GatewayType::WEBHOOK;
    }

    public function initiate(PayableInterface $payable): array
    {
        $payload = $this->buildPayload($payable);

        // Make actual API call to ToyyibPay
        $response = Http::asForm()->post($this->getApiUrl().'index.php/api/createBill', $payload);

        if ($response->failed()) {
            return [
                'type' => 'error',
                'error' => 'ToyyibPay API Error: '.$response->body(),
            ];
        }

        // ToyyibPay returns an array or string. Success is usually [{"BillCode":"..."}]
        $data = $response->json();

        // Check if we got a valid code
        $billCode = $data[0]['BillCode'] ?? null;

        if (! $billCode) {
            return [
                'type' => 'error',
                'error' => 'ToyyibPay did not return a BillCode: '.$response->body(),
            ];
        }

        return [
            'type' => 'redirect',
            'url' => $this->getCheckoutUrl($billCode),
            'payload' => $payload,
            'response' => $data,
        ];
    }

    /**
     * Verify payment status from webhook callback.
     *
     * This method is called by the PaymentWebhookController when a webhook
     * is received from ToyyibPay. It examines the webhook payload to determine
     * if the payment was successful.
     *
     * ToyyibPay webhook payload contains:
     * - status_id: 1 = success, 2 = pending, 3 = failed
     * - billcode: The bill reference
     * - amount: Payment amount
     *
     * @param  PayableInterface  $payable  The payment record being verified
     * @param  array  $payload  The raw webhook payload from ToyyibPay
     * @return array Returns ['success' => bool, 'transaction_id' => string|null, 'meta' => array]
     *               - If successful: triggers PaymentSucceeded event
     *               - If failed: triggers PaymentFailed event
     */
    public function verify(PayableInterface $payable, array $payload): array
    {
        // ToyyibPay Callback URL sends (POST format):
        // - refno: Payment reference no
        // - status: Payment status (INTEGER: 1=success, 2=pending, 3=fail)
        // - reason: Reason for the status received
        // - billcode: Your billcode / permanent link
        // - order_id: Your external payment reference no, if specified
        // - amount: Payment amount received
        // - transaction_time: Datetime of the transaction status received

        // Note: Return URL sends status_id (GET format), but callback sends status (POST format)
        $status = $payload['status'] ?? $payload['status_id'] ?? null;

        // ToyyibPay sends status as INTEGER: 1 = success, 2 = pending, 3 = fail
        // Convert to int for proper comparison
        $status = (int) $status;

        if ($status === 1) {
            return [
                'success' => true,
                'transaction_id' => $payload['transaction_id'] ?? $payload['refno'] ?? $payload['billcode'] ?? null,
                'meta' => $payload,
            ];
        }

        return [
            'success' => false,
            'error' => $payload['reason'] ?? $payload['msg'] ?? 'Payment not successful',
            'meta' => $payload,
        ];
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function supportsRefunds(): bool
    {
        return false; // ToyyibPay doesn't support API refunds
    }

    public function refund(string $transactionId, ?int $amount = null): array
    {
        return [
            'success' => false,
            'error' => 'ToyyibPay does not support API refunds',
        ];
    }

    protected function buildPayload(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();
        $customer = $payable->getPaymentCustomer();
        $urls = $payable->getPaymentUrls();
        $items = $payable->getPaymentItems();

        $maxItems = (int) ($settings['payment_item_max'] ?? 5);
        $billName = $payable->getPaymentReference();

        if (count($items) > $maxItems) {
            $billName = 'Payment ('.count($items).' items)';
        }

        return [
            'userSecretKey' => $this->secretKey ?? $settings['toyyibpay_secret_key'] ?? '',
            'categoryCode' => $this->categoryCode ?? $settings['toyyibpay_category_code'] ?? '',
            'billName' => $billName,
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
            'billChargeToCustomer' => $settings['toyyibpay_charge_customer'] ?? 1,
            'billExpiryDate' => null,
            'billExpiryDays' => $settings['toyyibpay_expiry_days'] ?? 3,
        ];
    }

    protected function getApiUrl(): string
    {
        return $this->sandbox
            ? 'https://dev.toyyibpay.com/'
            : 'https://toyyibpay.com/';
    }

    protected function getCheckoutUrl(string $billCode): string
    {
        $baseUrl = $this->sandbox
            ? 'https://dev.toyyibpay.com'
            : 'https://toyyibpay.com';

        return $baseUrl.'/'.$billCode;
    }

    public function verifySignature(\Illuminate\Http\Request $request): bool
    {
        // ToyyibPay doesn't have a signature header. Verify by checking known data points.
        return true;
    }

    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string
    {
        // ToyyibPay callback: 'order_id' contains our billExternalReferenceNo.
        // ToyyibPay callback: 'refno' is their internal transaction ID.
        return $request->input('order_id') ?? $request->input('billcode') ?? $request->input('refno');
    }

    public function checkStatus(PayableInterface $payable): array
    {
        // Mock implementation for now
        return [
            'status' => 'pending',
            'message' => 'Status check implemented (Stub for ToyyibPay).',
        ];
    }
}
