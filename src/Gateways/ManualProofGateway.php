<?php

namespace Ejoi8\MalaysiaPaymentGateway\Gateways;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Ejoi8\MalaysiaPaymentGateway\Enums\GatewayType;
use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse;
use Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult;
use Illuminate\Http\Request;

/**
 * Manual proof gateway (bank transfer with receipt upload).
 *
 * No automated verification — an administrator approves or rejects the proof.
 */
class ManualProofGateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'manual_proof';
    }

    public function getType(): GatewayType
    {
        return GatewayType::MANUAL;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function initiate(PayableInterface $payable): PaymentResponse
    {
        $settings = $payable->getPaymentSettings();

        $fields = [
            'message' => $settings['message']
                ?? $settings['manual_proof_message']
                ?? $this->setting('message')
                ?? 'Please make a bank transfer and upload your payment receipt.',
            'bank_info' => $settings['bank_info']
                ?? $settings['bank_account_info']
                ?? $this->setting('bank_info')
                ?? 'Contact administrator for bank details.',
            'reference' => $payable->getPaymentReference(),
            'amount' => $payable->getPaymentAmount(),
            'currency' => $payable->getPaymentCurrency(),
        ];

        return $this->instructions($fields, 'manual-'.$payable->getPaymentReference(), $fields);
    }

    public function verify(PayableInterface $payable, array $payload): VerificationResult
    {
        // Manual verification — approval is performed by an admin.
        if ($payload['approved'] ?? false) {
            return $this->verified(
                'manual-'.$payable->getPaymentReference(),
                array_merge($payload, ['verified_at' => now()->toDateTimeString()]),
            );
        }

        return $this->rejected($payload['rejection_reason'] ?? 'Payment proof rejected', $payload);
    }

    public function refund(string $transactionId, ?int $amount = null): VerificationResult
    {
        return $this->rejected('Manual proof payments must be refunded manually');
    }

    public function getPaymentIdFromRequest(Request $request): ?string
    {
        return $request->input('reference');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(PayableInterface $payable): array
    {
        // Status is managed internally, not via an external API.
        return [
            'status' => PaymentStatus::PENDING_VERIFICATION->value,
            'message' => 'Awaiting manual verification by administrator.',
        ];
    }
}
