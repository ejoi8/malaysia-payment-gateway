<?php

namespace Ejoi8\MalaysiaPaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Convenience facade for the payment gateway manager.
 *
 * @method static \Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface driver(?string $name = null)
 * @method static \Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface gateway(?string $name = null)
 * @method static \Ejoi8\MalaysiaPaymentGateway\Responses\PaymentResponse initiate(string $driver, \Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface $payable)
 * @method static \Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult verify(string $driver, \Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface $payable, array $payload)
 * @method static \Ejoi8\MalaysiaPaymentGateway\Responses\VerificationResult refund(string $driver, string $transactionId, ?int $amount = null)
 * @method static array checkStatus(string $driver, \Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface $payable)
 * @method static \Ejoi8\MalaysiaPaymentGateway\GatewayManager extend(string $name, callable $callback)
 * @method static array getAvailableDrivers()
 * @method static string getDefaultDriver()
 *
 * @see \Ejoi8\MalaysiaPaymentGateway\GatewayManager
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment-gateway';
    }
}
