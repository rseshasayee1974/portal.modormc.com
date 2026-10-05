<?php

namespace Tests\Feature;

use App\Models\{Machine, Patron, Plant, Product, ProductUnit, PurchaseOrder, PurchaseOrderHistory, PurchaseOrderItem, Quantity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Role;

class InwardDetailsTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderHistory $inward;
    private PurchaseOrderItem $item;
    private Quantity $stock;
    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create(['plant_id' => $this->plant->id, 'unit_id' => $unit->id]);
        $order = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id, 'vendor_id' => Patron::factory()->create()->id,
            'state' => 'approved', 'receipt_status' => 1,
        ]);
        $this->item = PurchaseOrderItem::factory()->create([
            'order_id' => $order->id, 'plant_id' => $this->plant->id, 'product_id' => $product->id,
            'product_uom' => $unit->id, 'product_quantity' => 100, 'received_quantity' => 10,
            'invoiced_quantity' => 0, 'discount_type' => '%', 'discount_amount' => 0,
        ]);
        $this->inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id, 'order_id' => $order->id, 'order_item_id' => $this->item->id,
            'product_id' => $product->id, 'uom_id' => $unit->id, 'received_date' => '2026-10-03',
            'inward_no' => 'INW-EDIT', 'received_qty' => 10, 'truck_loaded' => 100, 'truck_empty' => 20,
            'truck_id' => Machine::factory()->create(['plant_id' => $this->plant->id])->id, 'status' => 1,
        ]);
        $this->stock = Quantity::create([
            'plant_id' => $this->plant->id, 'product_id' => $product->id, 'uom_id' => $unit->id,
            'quantity' => 10, 'opening_quantity' => 0, 'status' => 1,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
    }

    private function updateDetails(array $payload)
    {
        return $this->post(route('inwards.update-weight', $this->inward), $payload);
    }

    public function test_truck_only_edit_preserves_accepted_quantity_and_stock_even_after_billing(): void
    {
        $this->item->update(['invoiced_quantity' => 10]);
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $this->updateDetails(['truck_id' => $truck->id])->assertSessionHasNoErrors();
        $this->assertEquals($truck->id, $this->inward->fresh()->truck_id);
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->item->fresh()->received_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['truck_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->inward->fresh()->truck_id);
    }

    public function test_received_unit_correction_moves_stock_into_existing_destination(): void
    {
        $unit = ProductUnit::factory()->create();
        $destination = Quantity::create([
            'plant_id' => $this->plant->id, 'product_id' => $this->inward->product_id,
            'uom_id' => $unit->id, 'quantity' => 5, 'opening_quantity' => 0, 'status' => 1,
        ]);
        $this->updateDetails(['uom_id' => $unit->id])->assertSessionHasNoErrors();
        $this->assertEquals($unit->id, $this->inward->fresh()->uom_id);
        $this->assertEquals(0, $this->stock->fresh()->quantity);
        $this->assertEquals(15, $destination->fresh()->quantity);
        $this->assertEquals(10, $this->item->fresh()->received_quantity);
    }

    public function test_insufficient_original_stock_rolls_back_all_details(): void
    {
        $this->stock->update(['quantity' => 3]);
        $unit = ProductUnit::factory()->create();
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $originalTruck = $this->inward->truck_id;
        $this->updateDetails(['uom_id' => $unit->id, 'truck_id' => $truck->id])->assertSessionHasErrors('uom_id');
        $this->assertEquals($originalTruck, $this->inward->fresh()->truck_id);
        $this->assertEquals($this->stock->uom_id, $this->inward->fresh()->uom_id);
        $this->assertEquals(3, $this->stock->fresh()->quantity);
        $this->assertDatabaseMissing('mm_quantity', ['product_id' => $this->inward->product_id, 'uom_id' => $unit->id]);
    }

    public function test_combined_unit_and_weight_edit_creates_stock_with_new_quantity(): void
    {
        $unit = ProductUnit::factory()->create();
        $this->updateDetails(['uom_id' => $unit->id, 'truck_empty' => 30])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->stock->fresh()->quantity);
        $this->assertEquals(70, $this->inward->fresh()->received_qty);
        $this->assertEquals(70, $this->item->fresh()->received_quantity);
        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id, 'product_id' => $this->inward->product_id,
            'uom_id' => $unit->id, 'quantity' => 70,
        ]);
    }

    public function test_billed_receipt_cannot_change_units(): void
    {
        $this->item->update(['invoiced_quantity' => 10]);
        $this->updateDetails(['uom_id' => ProductUnit::factory()->create()->id])->assertSessionHasErrors('inward');
        $this->assertEquals($this->stock->uom_id, $this->inward->fresh()->uom_id);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
    }

    public function test_conversion_edit_does_not_recalculate_accepted_quantity(): void
    {
        $unit = ProductUnit::factory()->create();
        $this->updateDetails(['conversion_uom_id' => $unit->id, 'conversion_quantity' => 2.5])->assertSessionHasNoErrors();
        $this->assertEquals($unit->id, $this->inward->fresh()->conversion_uom_id);
        $this->assertEquals(2.5, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['conversion_uom_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->inward->fresh()->conversion_uom_id);
    }

    public function test_foreign_plant_truck_and_receipt_are_rejected(): void
    {
        $foreign = Plant::factory()->create();
        $truck = Machine::factory()->create(['plant_id' => $foreign->id]);
        $this->updateDetails(['truck_id' => $truck->id])->assertSessionHasErrors('truck_id');
        $this->withSession(['active_plant_id' => $foreign->id]);
        $this->updateDetails(['truck_id' => null])->assertNotFound();
    }
}
