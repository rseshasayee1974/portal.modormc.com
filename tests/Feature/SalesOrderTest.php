<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\WorkOrder;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $superAdmin;
    protected Plant $plant;
    protected Patron $patron;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);
        
        $this->user = User::factory()->create();
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
            ['code' => 'SUPER_ADMIN']
        );
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($role);

        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create(['name' => 'Main Plant']);
        session(['active_plant_id' => $this->plant->id]);

        $this->patron = Patron::factory()->create(['legal_name' => 'Test Patron']);
        $this->site = Site::factory()->create(['name' => 'Test Site']);

        \App\Models\EntityUser::create([
            'user_id' => $this->superAdmin->id,
            'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id,
            'role_id' => $role->id,
        ]);

        $this->withSession([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    public function test_can_convert_quotation_to_sales_order()
    {
        $this->withoutExceptionHandling();
        $quotation = Quotation::factory()->create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'is_salesorder' => 0,
        ]);

        $response = $this->patch(route('quotations.convert', $quotation->id), [
            'is_salesorder' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        
        $quotation->refresh();
        $this->assertEquals(1, $quotation->is_salesorder);

        $salesOrder = SalesOrder::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals($this->user->id, $salesOrder->converted_by_user_id);
    }

    public function test_creating_sales_order_creates_sales_order_items()
    {
        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $quotation = Quotation::factory()->create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
        ]);
        
        $quotationItem = \App\Models\QuotationItem::create([
            'quotation_id' => $quotation->id,
            'mix_design_id' => $mixDesign->id,
            'quantity' => 10.5,
            'rate' => 500,
            'tax_amount' => 0,
            'untaxed_amount' => 5250,
            'amount_total' => 5250,
        ]);

        $response = $this->post(route('salesorders.store'), [
            'quotation_id' => $quotation->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(302);

        $salesOrder = SalesOrder::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($salesOrder);
        
        // Assert that sales order items were populated
        $this->assertCount(1, $salesOrder->items);
        $this->assertEquals($mixDesign->id, $salesOrder->items->first()->mix_design_id);
        $this->assertEquals(10.5, $salesOrder->items->first()->quantity);
        $this->assertEquals(500, $salesOrder->items->first()->rate);
    }

    public function test_can_convert_sales_order_to_work_order_with_quantity()
    {
        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $quotation = Quotation::factory()->create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
        ]);
        
        $quotationItem = \App\Models\QuotationItem::create([
            'quotation_id' => $quotation->id,
            'mix_design_id' => $mixDesign->id,
            'quantity' => 10.5,
            'rate' => 500,
            'tax_amount' => 0,
            'untaxed_amount' => 5250,
            'amount_total' => 5250,
        ]);

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'quotation_id' => $quotation->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ]);

        $salesOrder->items()->create([
            'mix_design_id' => $mixDesign->id,
            'quantity' => 10.5,
            'rate' => 500,
            'tax_amount' => 0,
            'untaxed_amount' => 5250,
            'amount_total' => 5250,
        ]);

        $response = $this->post(route('salesorders.convert-workorder', $salesOrder->id), [
            'quantity' => 5.5,
        ]);

        $response->assertStatus(302);

        $workOrder = WorkOrder::where('sales_order_id', $salesOrder->id)->first();
        $this->assertNotNull($workOrder);
        $this->assertEquals(5.5, $workOrder->total_qty);
    }

    public function test_direct_sales_order_creation_with_multiple_items()
    {
        $mixDesign1 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $mixDesign2 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->post(route('salesorders.store'), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'mix_design_id' => $mixDesign1->id,
                    'quantity' => 15.5,
                    'rate' => 300
                ],
                [
                    'mix_design_id' => $mixDesign2->id,
                    'quantity' => 25.0,
                    'rate' => 450
                ]
            ]
        ]);

        $response->assertStatus(302);

        $salesOrder = SalesOrder::whereNull('quotation_id')->first();
        $this->assertNotNull($salesOrder);
        $this->assertCount(2, $salesOrder->items);

        $firstItem = $salesOrder->items->first();
        $this->assertEquals($mixDesign1->id, $firstItem->mix_design_id);
        $this->assertEquals(15.5, $firstItem->quantity);
        $this->assertEquals(300, $firstItem->rate);
    }

    public function test_direct_sales_order_update_with_multiple_items()
    {
        $mixDesign1 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $mixDesign2 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        $response = $this->put(route('salesorders.update', $salesOrder->id), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 1,
            'items' => [
                [
                    'mix_design_id' => $mixDesign1->id,
                    'quantity' => 50,
                    'rate' => 400
                ],
                [
                    'mix_design_id' => $mixDesign2->id,
                    'quantity' => 100,
                    'rate' => 500
                ]
            ]
        ]);

        $response->assertStatus(302);
        
        $salesOrder->refresh();
        $this->assertCount(2, $salesOrder->items);
    }

    protected function clearGateBeforeCallbacks()
    {
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        $reflection = new \ReflectionClass($gate);
        $property = $reflection->getProperty('beforeCallbacks');
        $property->setAccessible(true);
        $property->setValue($gate, []);

        // Re-register Spatie's permission checking gate callback
        $gate->before(function ($user, $ability) {
            if (method_exists($user, 'hasPermissionTo')) {
                try {
                    return $user->hasPermissionTo($ability) ?: null;
                } catch (\Throwable $e) {
                    return null;
                }
            }
        });
    }

    public function test_normal_user_cannot_update_confirmed_sales_order()
    {
        $this->clearGateBeforeCallbacks();

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ]);

        $response = $this->put(route('salesorders.update', $salesOrder->id), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 1,
            'items' => []
        ]);

        $response->assertStatus(403);
    }

    public function test_normal_user_cannot_delete_confirmed_sales_order()
    {
        $this->clearGateBeforeCallbacks();

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ]);

        $response = $this->delete(route('salesorders.destroy', $salesOrder->id));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_confirmed_sales_order()
    {
        $this->clearGateBeforeCallbacks();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->superAdmin);

        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ]);

        $response = $this->put(route('salesorders.update', $salesOrder->id), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
            'items' => [
                [
                    'mix_design_id' => $mixDesign->id,
                    'quantity' => 10,
                    'rate' => 100
                ]
            ]
        ]);

        $response->assertStatus(302);
    }

    public function test_super_admin_can_delete_confirmed_sales_order()
    {
        $this->clearGateBeforeCallbacks();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->superAdmin);

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ]);

        $response = $this->delete(route('salesorders.destroy', $salesOrder->id));

        $response->assertStatus(302);
        $this->assertSoftDeleted($salesOrder);
    }
}
