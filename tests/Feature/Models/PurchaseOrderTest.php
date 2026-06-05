<?php

namespace Tests\Feature\Models;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderHistory;
use App\Models\PurchaseOrderItem;
use App\Models\Quantity;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $vendor;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // Create core master records needed for Purchase Orders
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->vendor = Patron::factory()->create();
        $this->currency = Currency::factory()->create();

        // Log in the user to satisfy Auth checks
        $this->actingAs($this->user);
    }

    /**
     * Test all basic relations of PurchaseOrder.
     */
    public function test_purchase_order_relations(): void
    {
        $vehicle = Machine::factory()->create();
        
        $purchaseOrder = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'vehicle_id' => $vehicle->id,
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $purchaseOrder->id,
            'plant_id' => $this->plant->id,
        ]);

        $bill = Invoice::factory()->create([
            'ref_id' => $purchaseOrder->id,
            'invoice_type' => 'bill',
        ]);

        $this->assertInstanceOf(Plant::class, $purchaseOrder->plant);
        $this->assertEquals($this->plant->id, $purchaseOrder->plant->id);

        $this->assertInstanceOf(Patron::class, $purchaseOrder->vendor);
        $this->assertEquals($this->vendor->id, $purchaseOrder->vendor->id);

        $this->assertInstanceOf(Machine::class, $purchaseOrder->vehicle);
        $this->assertEquals($vehicle->id, $purchaseOrder->vehicle->id);

        $this->assertInstanceOf(Currency::class, $purchaseOrder->currency);
        $this->assertEquals($this->currency->id, $purchaseOrder->currency->id);

        $this->assertInstanceOf(User::class, $purchaseOrder->creator);
        $this->assertEquals($this->user->id, $purchaseOrder->creator->id);

        $this->assertInstanceOf(User::class, $purchaseOrder->modifier);
        $this->assertEquals($this->user->id, $purchaseOrder->modifier->id);

        $this->assertCount(1, $purchaseOrder->items);
        $this->assertEquals($item->id, $purchaseOrder->items->first()->id);

        $this->assertInstanceOf(Invoice::class, $purchaseOrder->bill);
        $this->assertEquals($bill->id, $purchaseOrder->bill->id);
    }

    /**
     * Test recalculateTotals operation.
     */
    public function test_recalculate_totals(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'discount_amount' => 50.00,
            'shipping_charges' => 20.00,
            'adjustment' => 5.00,
        ]);

        // Create items with subtotal and tax
        PurchaseOrderItem::factory()->create([
            'order_id' => $purchaseOrder->id,
            'price_subtotal' => 100.00,
            'price_tax' => 18.00,
        ]);

        PurchaseOrderItem::factory()->create([
            'order_id' => $purchaseOrder->id,
            'price_subtotal' => 200.00,
            'price_tax' => 36.00,
        ]);

        $purchaseOrder->recalculateTotals();

        // amount_untaxed = (100 + 200) - discount_amount(50) = 250
        $this->assertEquals(250.00, $purchaseOrder->amount_untaxed);
        // amount_tax = 18 + 36 = 54
        $this->assertEquals(54.00, $purchaseOrder->amount_tax);
        // amount_total = amount_untaxed(250) + amount_tax(54) + shipping(20) + adjustment(5) = 329
        $this->assertEquals(329.00, $purchaseOrder->amount_total);
    }

    /**
     * Test generating next sequential PO ref ID.
     */
    public function test_generate_next_ref_id(): void
    {
        $date = '2026-06-05';
        $finYear = PurchaseOrder::getFinancialYearString($date);
        $prefix = "PO-{$finYear}-";

        // Generate PO when none exists
        $refData1 = PurchaseOrder::generateNextRefId($this->plant->id, $date);
        $this->assertEquals($prefix . '0001', $refData1['ref_no']);

        // Create one PO
        PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'po_number' => $prefix . '0001',
        ]);

        // Next should be 0002
        $refData2 = PurchaseOrder::generateNextRefId($this->plant->id, $date);
        $this->assertEquals($prefix . '0002', $refData2['ref_no']);
    }

    /**
     * Test storing PO with its items.
     */
    public function test_store_with_items(): void
    {
        $product = Product::factory()->create();
        $tax = Tax::factory()->create(['tax_rate' => 18.00]);

        $data = [
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'date_order' => '2026-06-05',
            'discount_amount' => 10.00,
            'currency_id' => $this->currency->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_uom' => $product->unit_id ?? 1,
                    'tax_id' => $tax->id,
                    'product_quantity' => 10,
                    'unit_price' => 50,
                ]
            ]
        ];

        $po = PurchaseOrder::storeWithItems($data);

        $this->assertDatabaseHas('mm_purchase_orders', [
            'id' => $po->id,
            'vendor_id' => $this->vendor->id,
            'discount_amount' => 10.00,
        ]);

        $this->assertCount(1, $po->items);
        
        $item = $po->items->first();
        // qty: 10, unit_price: 50 -> subtotal 500, discount: 0 (no item discount), tax: 18% of 500 = 90
        $this->assertEquals(500.00, $item->price_subtotal);
        $this->assertEquals(90.00, $item->price_tax);
        $this->assertEquals(590.00, $item->price_total);

        // header amount_untaxed: 500 - global discount (10) = 490
        $this->assertEquals(490.00, $po->amount_untaxed);
        $this->assertEquals(90.00, $po->amount_tax);
        $this->assertEquals(580.00, $po->amount_total);
    }

    /**
     * Test storeWithItems fails if active plant is missing.
     */
    public function test_store_with_items_throws_exception_if_no_plant(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Active plant is required to create a purchase order.');

        PurchaseOrder::storeWithItems([
            'vendor_id' => $this->vendor->id,
            'date_order' => '2026-06-05',
        ]);
    }

    /**
     * Test updateWithItems updates header and syncs items.
     */
    public function test_update_with_items(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        $tax = Tax::factory()->create(['tax_rate' => 10.00]);

        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'discount_amount' => 20.00,
        ]);

        $itemToUpdate = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $product1->id,
            'product_quantity' => 5,
            'unit_price' => 100,
            'tax_id' => $tax->id,
            'price_subtotal' => 500,
            'price_tax' => 50,
            'price_total' => 550,
        ]);

        $itemToDelete = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
        ]);

        $updatePayload = [
            'vendor_id' => $this->vendor->id,
            'discount_amount' => 50.00,
            'items' => [
                // Update itemToUpdate
                [
                    'id' => $itemToUpdate->id,
                    'product_id' => $product1->id,
                    'product_uom' => $product1->unit_id ?? 1,
                    'product_quantity' => 10, // increased quantity
                    'unit_price' => 100,
                    'tax_id' => $tax->id,
                ],
                // Add new item
                [
                    'product_id' => $product2->id,
                    'product_uom' => $product2->unit_id ?? 1,
                    'product_quantity' => 2,
                    'unit_price' => 50,
                    'tax_id' => $tax->id,
                ]
                // itemToDelete is omitted, so it should be soft deleted
            ]
        ];

        $po->updateWithItems($updatePayload);

        $this->assertEquals(50.00, $po->discount_amount);

        // Check itemToUpdate is updated
        $this->assertDatabaseHas('mm_purchase_order_items', [
            'id' => $itemToUpdate->id,
            'product_quantity' => 10,
            'price_subtotal' => 1000,
        ]);

        // Check new item is created
        $this->assertDatabaseHas('mm_purchase_order_items', [
            'order_id' => $po->id,
            'product_id' => $product2->id,
            'product_quantity' => 2,
        ]);

        // Check itemToDelete is soft-deleted
        $this->assertSoftDeleted('mm_purchase_order_items', [
            'id' => $itemToDelete->id
        ]);
    }

    /**
     * Test receiveItemQuantity method on PurchaseOrder.
     */
    public function test_receive_item_quantity(): void
    {
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->create();

        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'product_uom' => $unit->id,
            'product_quantity' => 10,
            'received_quantity' => 2,
            'unit_price' => 50.00,
        ]);

        // Cast to TestPurchaseOrder wrapper to access receiveItemQuantity
        $testPo = TestPurchaseOrder::find($po->id);

        // Receive 5 units
        $testPo->testReceiveItemQuantity($item, 5.0, '2026-06-05', 'INW-TEST-01', $this->user->id);

        // Check item quantity is updated: 2 (existing) + 5 (received) = 7
        $item->refresh();
        $this->assertEquals(7.0, (float)$item->received_quantity);

        // Check history was logged
        $this->assertDatabaseHas('mm_purchase_order_history', [
            'order_id' => $po->id,
            'order_item_id' => $item->id,
            'received_qty' => 5.0,
            'used_quantity' => 7.0, // used_quantity in history stores the running total (newReceivedQty)
            'inward_no' => 'INW-TEST-01',
        ]);

        // Check stock Quantity was recorded
        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'quantity' => 5.0,
        ]);
    }

    /**
     * Test that receiving over the remaining ordered quantity only receives up to remaining quantity.
     */
    public function test_receive_item_quantity_caps_at_ordered_quantity(): void
    {
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->create();

        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'product_uom' => $unit->id,
            'product_quantity' => 10,
            'received_quantity' => 8,
            'unit_price' => 50.00,
        ]);

        $testPo = TestPurchaseOrder::find($po->id);

        // Try to receive 5 units, but remaining is only 2 (10 - 8)
        $testPo->testReceiveItemQuantity($item, 5.0, '2026-06-05', 'INW-TEST-02', $this->user->id);

        $item->refresh();
        // Received quantity should be capped at 10 (8 + 2)
        $this->assertEquals(10.0, (float)$item->received_quantity);

        // History received_qty should be 2.0 (the accepted quantity)
        $this->assertDatabaseHas('mm_purchase_order_history', [
            'order_id' => $po->id,
            'order_item_id' => $item->id,
            'received_qty' => 2.0,
            'used_quantity' => 10.0,
        ]);

        // Stock quantity should be updated by 2.0
        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'quantity' => 2.0,
        ]);
    }

    /**
     * Test refreshReceiptStatus method updates status correctly.
     */
    public function test_refresh_receipt_status(): void
    {
        $po = PurchaseOrder::factory()->create([
            'receipt_status' => 0,
        ]);

        $item1 = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'product_quantity' => 10,
            'received_quantity' => 0,
        ]);

        $testPo = TestPurchaseOrder::find($po->id);

        // No quantity received -> receipt_status = 0
        $testPo->testRefreshReceiptStatus();
        $this->assertEquals(0, $testPo->receipt_status);

        // Partial receipt -> receipt_status = 1
        $item1->update(['received_quantity' => 5]);
        $testPo->testRefreshReceiptStatus();
        $this->assertEquals(1, $testPo->receipt_status);

        // Full receipt -> receipt_status = 2
        $item1->update(['received_quantity' => 10]);
        $testPo->testRefreshReceiptStatus();
        $this->assertEquals(2, $testPo->receipt_status);
    }
}

/**
 * Subclass wrapper to expose protected methods of PurchaseOrder for test assertions.
 */
class TestPurchaseOrder extends PurchaseOrder
{
    protected $table = 'mm_purchase_orders';

    public function testReceiveItemQuantity(
        PurchaseOrderItem $item,
        float $receivedNow,
        ?string $receivedDate,
        ?string $inwardNo,
        int $userId
    ): void {
        $this->receiveItemQuantity($item, $receivedNow, $receivedDate, $inwardNo, $userId);
    }

    public function testRefreshReceiptStatus(): void
    {
        $this->refreshReceiptStatus();
    }
}
