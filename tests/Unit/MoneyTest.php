<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Support\Money;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_cents_to_a_decimal_string(): void
    {
        $this->assertSame('55.00', Money::toDecimal(5500));
        $this->assertSame('0.99', Money::toDecimal(99));
        $this->assertSame('100.00', Money::toDecimal(10000));
        $this->assertSame('0.00', Money::toDecimal(0));
    }

    public function test_it_supports_a_custom_number_of_decimals(): void
    {
        $this->assertSame('5.500', Money::toDecimal(5500, 3));
    }
}
