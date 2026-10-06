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

    public function test_convert_volume_is_saved_and_only_recalculates_billing_quantity(): void
    {
        $this->updateDetails(['convert_volume' => 4.5, 'conversion_quantity' => 999])->assertSessionHasNoErrors();
        $this->assertEquals(4.5, $this->inward->fresh()->convert_volume);
        $this->assertEquals(2.2222, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['received_qty' => 18])->assertSessionHasNoErrors();
        $this->assertEquals(4, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(18, $this->stock->fresh()->quantity);
        $this->updateDetails(['convert_volume' => 0])->assertSessionHasErrors('convert_volume');
        $this->updateDetails(['convert_volume' => -1])->assertSessionHasErrors('convert_volume');
        $this->item->update(['invoiced_quantity' => 18]);
        $this->updateDetails(['convert_volume' => 2])->assertSessionHasErrors('inward');
    }

    public function test_direct_stock_quantity_update_preserves_weights_and_explicit_billing_conversion(): void
    {
        $this->updateDetails(['received_qty' => 15, 'conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals(15, $this->inward->fresh()->received_qty);
        $this->assertEquals(15, $this->item->fresh()->received_quantity);
        $this->assertEquals(15, $this->stock->fresh()->quantity);
        $this->assertEquals(3, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(100, $this->inward->fresh()->truck_loaded);
        $this->assertEquals(20, $this->inward->fresh()->truck_empty);
    }

    public function test_unbilled_inward_can_change_when_an_earlier_inward_is_billed(): void
    {
        $later = $this->inward->replicate();
        $later->inward_no = 'INW-LATER';
        $later->save();
        $this->item->update(['received_quantity' => 20, 'invoiced_quantity' => 10]);
        $this->stock->update(['quantity' => 20]);
        $this->post(route('inwards.update-weight', $later), ['received_qty' => 15, 'conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals(15, $later->fresh()->received_qty);
        $this->assertEquals(25, $this->stock->fresh()->quantity);
        $this->assertEquals(10, $this->item->fresh()->invoiced_quantity);
        $this->updateDetails(['received_qty' => 5])->assertSessionHasErrors('inward');
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
