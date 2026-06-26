<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderHistory;
use App\Models\Quantity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderInwardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $vendor;
    protected ProductUnit $unit;
    protected Product $product;
    protected PurchaseOrder $po;
    protected PurchaseOrderItem $item;
    protected Machine $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->vendor = Patron::factory()->create();
        $this->unit = ProductUnit::factory()->create();
        $this->product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->truck = Machine::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        // Create approved Purchase Order
        $this->po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'state' => 'approved',
            'receipt_status' => 0,
        ]);

        $this->item = PurchaseOrderItem::factory()->create([
            'order_id' => $this->po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'product_quantity' => 100,
            'received_quantity' => 0,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        // Set session active context
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);

        // Give test user the bypass role to pass AuthorizesModule gate
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->user->assignRole($role);

        $this->actingAs($this->user);
    }

    public function test_index_page_renders_with_inertia(): void
    {
        // We need to mock VehiclesDropdown in context. Let's make sure the page loads.
        // If helper functions like VehiclesDropdown fail, they might need a real Machine.
        $response = $this->get(route('inwards.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Inwards/Index'));
    }

    public function test_create_page_renders_with_inertia(): void
    {
        $response = $this->get(route('inwards.create', $this->po->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Inwards/Create'));
    }

    public function test_store_records_inward_and_updates_stock_and_receipt_status(): void
    {
        $response = $this->post(route('inwards.store'), [
            'order_id' => $this->po->id,
            'received_date' => '2026-06-05',
            'inward_no' => 'INW-0001',
            'truck_id' => $this->truck->id,
            'truck_loaded' => 5000,
            'items' => [
                [
                    'order_item_id' => $this->item->id,
                    'received_qty' => 40,
                ]
            ]
        ]);

        $response->assertRedirect(route('inwards.index'));

        // Assert purchase order item received_quantity is updated
        $this->item->refresh();
        $this->assertEquals(40, $this->item->received_quantity);

        // Assert purchase order receipt_status is updated to 1 (partially received)
        $this->po->refresh();
        $this->assertEquals(1, $this->po->receipt_status);

        // Assert quantity (stock balance) is created/updated
        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 40,
        ]);

        // Assert inward history entry is created
        $this->assertDatabaseHas('mm_purchase_order_history', [
            'order_id' => $this->po->id,
            'order_item_id' => $this->item->id,
            'received_qty' => 40,
            'inward_no' => 'INW-0001',
        ]);
    }

    public function test_store_caps_received_qty_at_remaining_ordered_qty(): void
    {
        // Ordering 100, received 0. Remaining is 100.
        // We try to receive 150.
        $response = $this->post(route('inwards.store'), [
            'order_id' => $this->po->id,
            'received_date' => '2026-06-05',
            'inward_no' => 'INW-0002',
            'items' => [
                [
                    'order_item_id' => $this->item->id,
                    'received_qty' => 150, // More than remaining (100)
                ]
            ]
        ]);

        $response->assertRedirect(route('inwards.index'));

        // Assert quantity is capped at 100
        $this->item->refresh();
        $this->assertEquals(100, $this->item->received_quantity);

        // Assert purchase order receipt_status is 2 (fully received)
        $this->po->refresh();
        $this->assertEquals(2, $this->po->receipt_status);

        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
        ]);

        $this->assertDatabaseHas('mm_purchase_order_history', [
            'order_id' => $this->po->id,
            'received_qty' => 100,
        ]);
    }

    public function test_destroy_inward_reverts_stock_and_po_receipt_status(): void
    {
        // First record an inward of 40
        $inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id,
            'order_id' => $this->po->id,
            'order_item_id' => $this->item->id,
            'received_date' => '2026-06-05',
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'received_qty' => 40,
            'used_quantity' => 40,
            'unit_price' => $this->item->unit_price,
            'inward_no' => 'INW-0003',
            'status' => 1,
        ]);

        $this->item->update(['received_quantity' => 40]);
        $this->po->update(['receipt_status' => 1]);

        // Populate stock balance
        Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 40,
            'opening_quantity' => 0,
            'status' => 1,
        ]);

        // Destroy inward
        $response = $this->delete(route('inwards.destroy', $inward->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check database for deletion and adjustments
        $this->assertNull(PurchaseOrderHistory::find($inward->id));

        $this->item->refresh();
        $this->assertEquals(0, $this->item->received_quantity);

        $this->po->refresh();
        $this->assertEquals(0, $this->po->receipt_status);

        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);
    }

    public function test_destroy_fails_if_stock_consumed(): void
    {
        // Record inward of 40
        $inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id,
            'order_id' => $this->po->id,
            'order_item_id' => $this->item->id,
            'received_date' => '2026-06-05',
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'received_qty' => 40,
            'used_quantity' => 40,
            'unit_price' => $this->item->unit_price,
            'inward_no' => 'INW-0004',
            'status' => 1,
        ]);

        $this->item->update(['received_quantity' => 40]);

        // Stock balance is only 10 (30 items consumed by other processes)
        Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 10,
            'opening_quantity' => 0,
            'status' => 1,
        ]);

        $response = $this->delete(route('inwards.destroy', $inward->id));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Inward record cannot be deleted because the stock has already been consumed.');

        // Verify inward was NOT deleted
        $this->assertNotNull(PurchaseOrderHistory::find($inward->id));
    }

    public function test_update_weight_recalculates_net_weight_and_updates_stock(): void
    {
        // Create inward from loaded truck (truck_loaded = 5000, received_qty = 5000 initially)
        $inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id,
            'order_id' => $this->po->id,
            'order_item_id' => $this->item->id,
            'received_date' => '2026-06-05',
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'received_qty' => 5000,
            'used_quantity' => 5000,
            'truck_id' => $this->truck->id,
            'truck_loaded' => 5000,
            'inward_no' => 'INW-0005',
            'status' => 1,
        ]);

        $this->item->update(['received_quantity' => 5000]);

        Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 5000,
            'opening_quantity' => 0,
            'status' => 1,
        ]);

        // Record truck empty weight = 1500. Net weight should become 5000 - 1500 = 3500.
        // Difference is -1500. Stock should reduce from 5000 to 3500.
        $response = $this->post(route('inwards.update-weight', $inward->id), [
            'truck_empty' => 1500,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $inward->refresh();
        $this->assertEquals(1500, $inward->truck_empty);
        $this->assertEquals(3500, $inward->received_qty);

        $this->item->refresh();
        $this->assertEquals(3500, $this->item->received_quantity);

        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'quantity' => 3500,
        ]);
    }

    public function test_update_weight_fails_if_reduces_stock_below_zero(): void
    {
        // Truck loaded = 5000, received_qty = 5000 initially
        $inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id,
            'order_id' => $this->po->id,
            'order_item_id' => $this->item->id,
            'received_date' => '2026-06-05',
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'received_qty' => 5000,
            'used_quantity' => 5000,
            'truck_id' => $this->truck->id,
            'truck_loaded' => 5000,
            'inward_no' => 'INW-0006',
            'status' => 1,
        ]);

        $this->item->update(['received_quantity' => 5000]);

        // Suppose stock is consumed and only 1000 is left in stock
        Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 1000,
            'opening_quantity' => 0,
            'status' => 1,
        ]);

        // Truck empty = 4500. Net weight becomes 5000 - 4500 = 500.
        // Difference is -4500. But stock is only 1000.
        // Reducing stock by 4500 would result in negative stock (-3500), which throws an InvalidArgumentException.
        $this->expectException(\InvalidArgumentException::class);

        $this->withoutExceptionHandling();
        $this->post(route('inwards.update-weight', $inward->id), [
            'truck_empty' => 4500,
        ]);
    }
}
