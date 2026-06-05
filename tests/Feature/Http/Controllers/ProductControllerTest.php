<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected ProductUnit $unit;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->unit = ProductUnit::factory()->create();
        $this->product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
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

    public function test_destroy_deletes_product_when_no_dependencies(): void
    {
        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->delete(route('products.destroy', $product->id));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Product deleted successfully.');
        $this->assertSoftDeleted($product);
    }

    public function test_destroy_fails_when_product_has_purchase_order_items(): void
    {
        \App\Models\PurchaseOrderItem::factory()->create([
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_purchase_order_history(): void
    {
        \App\Models\PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id,
            'order_id' => 1,
            'order_item_id' => 1,
            'received_date' => now(),
            'product_id' => $this->product->id,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_quantities(): void
    {
        \App\Models\Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_batch_materials(): void
    {
        \App\Models\BatchMaterial::create([
            'batch_id' => 1,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_maintenance_lines(): void
    {
        $vendor = \App\Models\Patron::factory()->create(['plant_id' => $this->plant->id]);
        $machine = \App\Models\Machine::factory()->create(['plant_id' => $this->plant->id]);
        $request = \App\Models\MaintenanceRequest::create([
            'name' => 'Maint Req',
            'description' => 'Desc',
            'machine_id' => $machine->id,
            'plant_id' => $this->plant->id,
            'inventory_req_lines' => '[]',
            'maintanence_type' => 1,
            'priority' => 1,
            'responsible_id' => $this->user->id,
            'repair_location' => 'In-House',
            'repair_vendor_id' => $vendor->id,
            'status' => 1,
            'dead_line' => now()->addDays(5),
            'start_date' => now(),
            'end_date' => now()->addDays(1),
        ]);

        \App\Models\MaintenanceLine::create([
            'order_id' => $request->id,
            'plant_id' => $this->plant->id,
            'partner_id' => $vendor->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'name' => 'Oil Filter',
            'product_quantity' => '1',
            'invoiced_quantity' => '0',
            'received_quantity' => '0',
            'date_planned' => now(),
            'price_unit' => 100.00,
            'price_subtotal' => 100.00,
            'price_tax' => 18.00,
            'price_total' => 118.00,
            'status' => 1,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_stock_exhaust_lines(): void
    {
        \App\Models\StockExhaustLine::create([
            'stock_id' => 1,
            'product_id' => $this->product->id,
            'no_items_issued' => 5,
            'issue_date' => now(),
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_party_rates(): void
    {
        \App\Models\PartyRate::factory()->create([
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_concrete_grade_items(): void
    {
        \App\Models\ConcreteGradeItem::create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => 1,
            'product_id' => $this->product->id,
            'ratio' => 1.5,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }

    public function test_destroy_fails_when_product_has_mix_design_items(): void
    {
        $mixDesign = \App\Models\MixDesign::create([
            'plant_id' => $this->plant->id,
            'partner_id' => \App\Models\Patron::factory()->create(['plant_id' => $this->plant->id])->id,
            'design_name' => 'Test Design',
            'design_code' => 'TEST-01',
        ]);

        \App\Models\MixDesignItem::create([
            'plant_id' => $this->plant->id,
            'mix_design_id' => $mixDesign->id,
            'product_id' => $this->product->id,
            'uom_id' => $this->unit->id,
        ]);

        $response = $this->delete(route('products.destroy', $this->product->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('mm_products', ['id' => $this->product->id, 'deleted_at' => null]);
    }
}
