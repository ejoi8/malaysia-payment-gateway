<?php

namespace Ejoi8\MalaysiaPaymentGateway\Support;

use Ejoi8\MalaysiaPaymentGateway\Contracts\GatewayInterface;
use InvalidArgumentException;

class GatewayFactory
{
    public static function make(array $config): GatewayInterface
    {
        $class = $config['driver_class'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException('Gateway driver class is not configured or does not exist.');
        }

        $gateway = $class::make($config);

        if (! $gateway instanceof GatewayInterface) {
            throw new InvalidArgumentException("Gateway driver [{$class}] must implement GatewayInterface.");
        }

        return $gateway;
    }
}
