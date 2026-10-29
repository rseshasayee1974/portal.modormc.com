<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 13:11:33 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Plant, Product, ProductUnit, Quantity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class ProductOpeningQuantityTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $plant = Plant::factory()->create();
        $this->product = Product::factory()->create([
            'plant_id' => $plant->id, 'is_service' => false,
            'unit_id' => ProductUnit::factory()->create()->id,
        ]);
        $user->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession([
            'active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id,
        ]);
    }

    private function setOpening(mixed $quantity)
    {
        return $this->patch(route('products.opening-quantity', $this->product), [
            'opening_quantity' => $quantity,
        ]);
    }

    private function stock(float $opening = 10, float $quantity = 15): Quantity
    {
        return Quantity::create([
            'plant_id' => $this->product->plant_id, 'product_id' => $this->product->id,
            'uom_id' => $this->product->unit_id, 'opening_quantity' => $opening,
            'quantity' => $quantity, 'date' => '2026-01-01', 'status' => 1,
        ]);
    }

    public function test_creates_stock_and_repeated_save_does_not_add_stock_twice(): void
    {
        $this->setOpening('12.50')->assertRedirect()->assertSessionHasNoErrors();
        $this->setOpening('12.50')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('mm_quantity', 1);
        $this->assertDatabaseHas('mm_quantity', [
            'product_id' => $this->product->id, 'uom_id' => $this->product->unit_id,
            'opening_quantity' => 12.5, 'quantity' => 12.5,
        ]);
    }

    public function test_replacing_opening_preserves_movements_date_and_clears_dashboard_cache(): void
    {
        $stock = $this->stock();
        $key = "inventory.dashboard.data.{$this->product->plant_id}";
        Cache::put($key, ['stale'], 300);
        $this->setOpening(20)->assertSessionHasNoErrors();
        $this->assertEquals(25, $stock->fresh()->quantity);
        $this->assertEquals(20, $stock->fresh()->opening_quantity);
        $this->assertSame('2026-01-01', $stock->fresh()->date->toDateString());
        $this->assertNull(Cache::get($key));
        $this->setOpening(0)->assertSessionHasNoErrors();
        $this->assertEquals(5, $stock->fresh()->quantity);
    }

    public function test_cannot_reduce_opening_below_consumed_stock(): void
    {
        $stock = $this->stock(10, 2);
        $this->setOpening(1)->assertSessionHasErrors('opening_quantity');
        $this->assertEquals(10, $stock->fresh()->opening_quantity);
        $this->assertEquals(2, $stock->fresh()->quantity);
    }

    public function test_rejects_invalid_quantities(): void
    {
        foreach ([-1, 'abc', null, '1.234', '1000000000000000'] as $value) {
            $this->setOpening($value)->assertSessionHasErrors('opening_quantity');
        }
        $this->assertDatabaseCount('mm_quantity', 0);
    }

    public function test_service_product_cannot_have_opening_stock(): void
    {
        $this->product->update(['is_service' => true]);
        $this->setOpening(10)->assertSessionHasErrors('opening_quantity');
        $this->assertDatabaseCount('mm_quantity', 0);
    }

    public function test_unit_is_required(): void
    {
        $this->product->update(['unit_id' => null]);
        $this->setOpening(10)->assertSessionHasErrors('opening_quantity');
    }

    public function test_cannot_modify_another_plants_stock(): void
    {
        $this->withSession(['active_plant_id' => $this->product->plant_id + 100]);
        $response = $this->setOpening(10);
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseCount('mm_quantity', 0);
    }

    public function test_update_permission_is_required(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setOpening(10)->assertForbidden();
        $this->assertDatabaseCount('mm_quantity', 0);
    }

    public function test_other_units_and_warehouse_stock_are_unchanged(): void
    {
        $stock = $this->stock();
        $other = $this->stock(3, 8);
        $other->update(['uom_id' => ProductUnit::factory()->create()->id]);
        $warehouse = $this->stock(4, 9);
        $warehouse->update(['is_warehouse' => true]);
        $this->setOpening(20)->assertSessionHasNoErrors();
        $this->assertEquals(25, $stock->fresh()->quantity);
        $this->assertEquals(8, $other->fresh()->quantity);
        $this->assertEquals(9, $warehouse->fresh()->quantity);
    }
}
