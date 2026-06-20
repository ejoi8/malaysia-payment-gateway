<?php

namespace Ejoi8\MalaysiaPaymentGateway\Exceptions;

use RuntimeException;

/**
 * Thrown when initiate() is called on a payment that has already been paid,
 * preventing an accidental second charge (e.g. a double-clicked pay button or a
 * stale checkout link).
 */
class PaymentAlreadyProcessedException extends RuntimeException
{
}
