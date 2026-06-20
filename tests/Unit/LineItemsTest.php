<?php

namespace Ejoi8\MalaysiaPaymentGateway\Tests\Unit;

use Ejoi8\MalaysiaPaymentGateway\Support\LineItems;
use Ejoi8\MalaysiaPaymentGateway\Tests\MockPayable;
use Ejoi8\MalaysiaPaymentGateway\Tests\TestCase;

class LineItemsTest extends TestCase
{
    public function test_unit_count_sums_quantities(): void
    {
        $this->assertSame(2, LineItems::unitCount([['quantity' => 1], ['quantity' => 1]]));
        $this->assertSame(3, LineItems::unitCount([['quantity' => 3]]));
        $this->assertSame(3, LineItems::unitCount([['quantity' => 2], ['quantity' => 1]]));
        $this->assertSame(2, LineItems::unitCount([['name' => 'a'], ['name' => 'b']])); // qty defaults to 1
        $this->assertSame(0, LineItems::unitCount([]));
    }

    public function test_summary_name_appends_the_item_count(): void
    {
        $payable = new MockPayable(description: 'Order ORD-1', items: [
            ['name' => 'A', 'quantity' => 1, 'price' => 100],
            ['name' => 'B', 'quantity' => 2, 'price' => 100],
        ]);

        $this->assertSame('Order ORD-1 (3 items)', LineItems::summaryName($payable));
    }

    public function test_summary_name_has_no_suffix_for_a_single_unit(): void
    {
        $payable = new MockPayable(description: 'Court Booking', items: [
            ['name' => 'Court', 'quantity' => 1, 'price' => 5000],
        ]);

        $this->assertSame('Court Booking', LineItems::summaryName($payable));
    }

    public function test_summary_name_respects_config(): void
    {
        $payable = new MockPayable(description: 'Conf', items: [
            ['name' => 'Ticket', 'quantity' => 3, 'price' => 100],
        ]);

        config(['payment-gateway.gateway_line.label' => 'tickets']);
        $this->assertSame('Conf (3 tickets)', LineItems::summaryName($payable));

        config(['payment-gateway.gateway_line.append_count' => false]);
        $this->assertSame('Conf', LineItems::summaryName($payable));
    }
}
