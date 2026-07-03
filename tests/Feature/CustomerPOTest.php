<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Quotation;
use App\Models\CustomerPO;
use App\Models\SalesOrder;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPOTest extends TestCase
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

    public function test_can_convert_quotation_to_customer_po()
    {
        $this->withoutExceptionHandling();
        $quotation = Quotation::factory()->create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'is_customer_po' => 0,
        ]);

        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $quotationItem = \App\Models\QuotationItem::create([
            'quotation_id' => $quotation->id,
            'mix_design_id' => $mixDesign->id,
            'quantity' => 12.5,
            'rate' => 600,
            'tax_amount' => 0,
            'untaxed_amount' => 7500,
            'amount_total' => 7500,
        ]);

        $response = $this->patch(route('quotations.convert', $quotation->id), [
            'is_customer_po' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(302);
        
        $quotation->refresh();
        $this->assertEquals(1, $quotation->is_customer_po);

        $customerPO = CustomerPO::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($customerPO);
        $this->assertEquals($this->user->id, $customerPO->converted_by_user_id);

        $this->assertCount(1, $customerPO->items);
        $this->assertEquals(12.5, $customerPO->items->first()->quantity);
        $this->assertEquals(600, $customerPO->items->first()->rate);
    }

    public function test_creating_customer_po_creates_customer_po_items()
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

        $response = $this->post(route('customer-po.store'), [
            'quotation_id' => $quotation->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(302);

        $customerPO = CustomerPO::where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($customerPO);
        
        // Assert that customer PO items were populated
        $this->assertCount(1, $customerPO->items);
        $this->assertEquals($mixDesign->id, $customerPO->items->first()->mix_design_id);
        $this->assertEquals(10.5, $customerPO->items->first()->quantity);
        $this->assertEquals(500, $customerPO->items->first()->rate);
    }

    public function test_can_convert_customer_po_to_sales_order_with_quantity()
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

        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'quotation_id' => $quotation->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_DRAFT,
        ]);

        $customerPO->items()->create([
            'mix_design_id' => $mixDesign->id,
            'quantity' => 10.5,
            'rate' => 500,
            'tax_amount' => 0,
            'untaxed_amount' => 5250,
            'amount_total' => 5250,
        ]);

        $response = $this->post(route('customer-po.convert-salesorder', $customerPO->id), [
            'quantity' => 5.5,
        ]);

        $response->assertStatus(302);

        $customerPO->refresh();
        $this->assertEquals(CustomerPO::STATUS_CONFIRMED, (int)$customerPO->status);

        $salesOrder = SalesOrder::where('customer_po_id', $customerPO->id)->first();
        $this->assertNotNull($salesOrder);
        $this->assertEquals(5.5, $salesOrder->total_qty);
    }

    public function test_direct_customer_po_creation_with_multiple_items()
    {
        $mixDesign1 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $mixDesign2 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->post(route('customer-po.store'), [
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

        $customerPO = CustomerPO::whereNull('quotation_id')->first();
        $this->assertNotNull($customerPO);
        $this->assertCount(2, $customerPO->items);

        $firstItem = $customerPO->items->first();
        $this->assertEquals($mixDesign1->id, $firstItem->mix_design_id);
        $this->assertEquals(15.5, $firstItem->quantity);
        $this->assertEquals(300, $firstItem->rate);
    }

    public function test_direct_customer_po_update_with_multiple_items()
    {
        $mixDesign1 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $mixDesign2 = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_DRAFT,
        ]);

        $response = $this->put(route('customer-po.update', $customerPO->id), [
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
        
        $customerPO->refresh();
        $this->assertCount(2, $customerPO->items);
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

    public function test_normal_user_cannot_update_confirmed_customer_po()
    {
        $this->clearGateBeforeCallbacks();

        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_CONFIRMED,
        ]);

        $response = $this->put(route('customer-po.update', $customerPO->id), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 1,
            'items' => []
        ]);

        $response->assertStatus(403);
    }

    public function test_normal_user_cannot_delete_confirmed_customer_po()
    {
        $this->clearGateBeforeCallbacks();

        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_CONFIRMED,
        ]);

        $response = $this->delete(route('customer-po.destroy', $customerPO->id));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_update_confirmed_customer_po()
    {
        $this->clearGateBeforeCallbacks();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->superAdmin);

        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);
        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_CONFIRMED,
        ]);

        $response = $this->put(route('customer-po.update', $customerPO->id), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_CONFIRMED,
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

    public function test_super_admin_can_delete_confirmed_customer_po()
    {
        $this->clearGateBeforeCallbacks();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->superAdmin);

        $customerPO = CustomerPO::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => CustomerPO::STATUS_CONFIRMED,
        ]);

        $response = $this->delete(route('customer-po.destroy', $customerPO->id));

        $response->assertStatus(302);
        $this->assertSoftDeleted($customerPO);
    }

    public function test_direct_customer_po_requires_valid_item_fields()
    {
        $response = $this->post(route('customer-po.store'), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'mix_design_id' => null,
                    'quantity' => null,
                    'rate' => null
                ]
            ]
        ]);

        $response->assertSessionHasErrors([
            'items.0.mix_design_id',
            'items.0.quantity',
            'items.0.rate'
        ]);
    }

    public function test_customer_po_reference_auto_generated_from_db_sequence()
    {
        $mixDesign = \App\Models\MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $response1 = $this->post(route('customer-po.store'), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'mix_design_id' => $mixDesign->id,
                    'quantity' => 10,
                    'rate' => 100
                ]
            ]
        ]);
        $response1->assertStatus(302);

        $response2 = $this->post(route('customer-po.store'), [
            'quotation_id' => null,
            'patron_id' => $this->patron->id,
            'site_id' => $this->site->id,
            'order_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'mix_design_id' => $mixDesign->id,
                    'quantity' => 20,
                    'rate' => 200
                ]
            ]
        ]);
        $response2->assertStatus(302);

        $pos = CustomerPO::orderBy('id', 'asc')->get();
        $this->assertCount(2, $pos);

        $po1 = $pos[0];
        $po2 = $pos[1];

        $expectedRef1 = sprintf('%s%04d', $po1->prefix, $po1->id);
        $expectedRef2 = sprintf('%s%04d', $po2->prefix, $po2->id);

        $this->assertEquals($expectedRef1, $po1->reference);
        $this->assertEquals($expectedRef2, $po2->reference);
        $this->assertNotEquals($po1->reference, $po2->reference);
    }
}
