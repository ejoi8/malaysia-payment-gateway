<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers\Concerns;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait ResolvesPayables
{
    protected function configuredPayableModel(): string
    {
        $modelClass = config('payment-gateway.model');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, PayableInterface::class)) {
            throw new RuntimeException('Payment Gateway: Model class not configured.');
        }

        return $modelClass;
    }

    protected function findPayable(string $reference): ?PayableInterface
    {
        $modelClass = $this->configuredPayableModel();

        return $modelClass::findByReference($reference);
    }

    protected function resolvePayableDriver(PayableInterface $payable): string
    {
        if ($payable instanceof Model) {
            return $payable->getAttribute('gateway')
                ?? $payable->getAttribute('payment_method')
                ?? config('payment-gateway.default', 'chip');
        }

        return $payable->gateway
            ?? $payable->payment_method
            ?? config('payment-gateway.default', 'chip');
    }
}
