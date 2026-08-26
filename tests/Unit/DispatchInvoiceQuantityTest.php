<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Dispatch;
use App\Models\Invoice;

class DispatchInvoiceQuantityTest extends TestCase
{
    public function test_dispatch_delivered_qty_is_preserved_in_created_invoice()
    {
        $dispatch = new Dispatch([
            'plant_id' => 1,
            'delivered_qty' => 2.500,
            'load_rate' => 145.00,
            'load_untax_amount' => 362.50,
            'load_tax_amount' => 65.25,
            'load_total_amount' => 427.75,
        ]);

        $items = $dispatch->items;

        $this->assertCount(1, $items);
        $this->assertEquals(2.5, $items->first()->quantity);

        $item = $items->first();
        $qty = (float) (data_get($item, 'quantity') ?? data_get($item, 'product_quantity') ?? 0);
        $this->assertEquals(2.5, $qty);
    }
}
