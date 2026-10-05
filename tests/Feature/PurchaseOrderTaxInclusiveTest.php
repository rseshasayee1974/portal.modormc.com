<?php

namespace Tests\Feature;

use App\Http\Controllers\PurchaseOrderController;
use App\Models\{Invoice, InvoiceItem, Patron, Plant, Product, ProductUnit, PurchaseOrder, Tax, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderTaxInclusiveTest extends TestCase
{
    use RefreshDatabase;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $plant = Plant::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create(['plant_id' => $plant->id, 'unit_id' => $unit->id]);
        $tax = Tax::factory()->create(['plant_id' => $plant->id, 'tax_type' => 'purchase', 'tax_rate' => 18]);
        $vendor = Patron::factory()->create(['plant_id' => $plant->id]);
        $this->actingAs($user)->withSession(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id]);
        $this->payload = [
            'plant_id' => $plant->id, 'vendor_id' => $vendor->id, 'date_order' => '2026-10-03',
            'po_number' => 'PO-TAX-001', 'discount_amount' => 0, 'shipping_charges' => 0,
            'adjustment' => 0, 'rounding_value' => 0,
            'items' => [[
                'product_id' => $product->id, 'product_uom' => $unit->id, 'tax_id' => $tax->id,
                'product_quantity' => 10, 'unit_price' => 118, 'discount_type' => '%', 'discount_amount' => 10,
            ]],
        ];
    }

    public function test_inclusive_rates_extract_tax_after_percentage_discount_and_survive_reload(): void
    {
        $order = PurchaseOrder::storeWithItems($this->payload + ['tax_inclusive' => true])->fresh();
        $item = $order->items()->firstOrFail();
        $this->assertTrue($order->tax_inclusive);
        $this->assertEquals(900, $item->price_subtotal);
        $this->assertEquals(162, $item->price_tax);
        $this->assertEquals(118, $item->total_discount);
        $this->assertEquals(1062, $item->price_total);
        $this->assertEquals(1062, $order->amount_total);
    }

    public function test_existing_exclusive_orders_keep_tax_added_and_header_toggle_recalculates_lines(): void
    {
        $order = PurchaseOrder::storeWithItems($this->payload);
        $this->assertFalse($order->fresh()->tax_inclusive);
        $this->assertEquals(1253.16, $order->amount_total);
        $order->updateWithItems(['tax_inclusive' => true]);
        $this->assertEquals(1062, $order->amount_total);
        $order->updateWithItems(['tax_inclusive' => false]);
        $this->assertEquals(1253.16, $order->amount_total);
    }

    public function test_fixed_discount_and_zero_tax_do_not_add_tax_twice(): void
    {
        $data = $this->payload;
        $data['items'][0]['discount_type'] = '₹';
        $data['items'][0]['discount_amount'] = 118;
        $order = PurchaseOrder::storeWithItems($data + ['tax_inclusive' => true]);
        $this->assertEquals(1062, $order->amount_total);
        $data['items'][0]['id'] = $order->items()->first()->id;
        $data['items'][0]['tax_id'] = null;
        $order->updateWithItems(['items' => $data['items']]);
        $this->assertEquals(0, $order->amount_tax);
        $this->assertEquals(1062, $order->amount_untaxed);
    }

    public function test_partial_receipt_bill_inherits_tax_mode_and_supports_a_different_bill_rate(): void
    {
        $order = PurchaseOrder::storeWithItems($this->payload + ['tax_inclusive' => true]);
        $item = $order->items()->firstOrFail();
        $item->update(['received_quantity' => 5, 'invoiced_quantity' => 0]);
        $request = Request::create('/', 'POST', ['items' => [['order_item_id' => $item->id, 'unit_price' => 236]]]);
        $bill = (new \ReflectionMethod(PurchaseOrderController::class, 'receivedBillData'))
            ->invoke(new PurchaseOrderController, $request, $order);
        $this->assertTrue($bill['is_tax_inclusive']);
        $this->assertEquals(900, $bill['subtotal']);
        $this->assertEquals(162, $bill['tax_amount']);
        $this->assertEquals(1062, $bill['total_amount']);
        // Generated invoice items must recompute to the same figures when persisted.
        $invoiceLine = new InvoiceItem($bill['items'][0]);
        $invoiceLine->compute(18, $bill['is_tax_inclusive']);
        $this->assertEquals($bill['items'][0]['subtotal'], $invoiceLine->subtotal);
        $this->assertEquals($bill['items'][0]['line_total'], $invoiceLine->line_total);
        $invoice = Invoice::createWithItems(array_merge($bill, ['status' => Invoice::STATUS_DRAFT]));
        $this->assertTrue($invoice->fresh()->is_tax_inclusive);
        $this->assertEquals(1062, $invoice->total_amount);
        $this->assertEquals(162, $invoice->tax_amount);
    }

    public function test_tax_mode_cannot_change_while_a_linked_bill_exists(): void
    {
        $order = PurchaseOrder::storeWithItems($this->payload);
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'plant_id' => $order->plant_id, 'ref_id' => $order->id,
            'invoice_type' => 'Bill', 'invoice_label' => 'purchase',
        ]));
        $this->expectException(ValidationException::class);
        $order->updateWithItems(['tax_inclusive' => true]);
    }
}
