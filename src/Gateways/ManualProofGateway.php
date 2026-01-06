<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;

/**
 * Manual proof gateway (bank transfer with receipt upload).
 */
class ManualProofGateway implements GatewayInterface
{
    public function __construct() {}

    public static function make(array $config): self
    {
        return new self();
    }

    public function getName(): string
    {
        return 'manual_proof';
    }

    public function getType(): GatewayType
    {
        return GatewayType::MANUAL;
    }

    public function initiate(PayableInterface $payable): array
    {
        $settings = $payable->getPaymentSettings();

        return [
            'type' => 'instructions',
            'message' => $settings['manual_proof_message'] ?? 'Please make a bank transfer and upload your payment receipt.',
            'bank_info' => $settings['bank_account_info'] ?? 'Contact administrator for bank details.',
            'reference' => $payable->getPaymentReference(),
            'amount' => $payable->getPaymentAmount(),
            'currency' => $payable->getPaymentCurrency(),
        ];
    }

    public function verify(PayableInterface $payable, array $payload): array
    {
        // Manual verification - approval is handled by admin
        $approved = $payload['approved'] ?? false;

        if ($approved) {
            return [
                'success' => true,
                'transaction_id' => 'manual-' . $payable->getPaymentReference(),
                'meta' => array_merge($payload, [
                    'verified_at' => now()->toDateTimeString(),
                ]),
            ];
        }

        return [
            'success' => false,
            'error' => $payload['rejection_reason'] ?? 'Payment proof rejected',
            'meta' => $payload,
        ];
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }

    public function refund(string $transactionId, ?int $amount = null): array
    {
        return [
            'success' => false,
            'error' => 'Manual proof payments must be refunded manually',
        ];
    }

    public function verifySignature(\Illuminate\Http\Request $request): bool
    {
        // Manual proof doesn't have webhooks, so signature is always valid
        return true;
    }

    public function getPaymentIdFromRequest(\Illuminate\Http\Request $request): ?string
    {
        // Manual proof uses reference from request
        return $request->input('reference');
    }

    public function checkStatus(PayableInterface $payable): array
    {
        // Manual proof status is managed internally, not via external API
        return [
            'status' => 'pending_verification',
            'message' => 'Awaiting manual verification by administrator.',
        ];
    }
}
