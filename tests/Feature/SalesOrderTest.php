<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SalesOrder;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Site;
use App\Models\MixDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $customer;
    protected Site $site;
    protected MixDesign $mixDesign;
    protected \App\Models\Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create(['name' => 'Main Plant']);
        session(['active_plant_id' => $this->plant->id]);

        $this->customer = Patron::factory()->create(['legal_name' => 'Test Customer']);
        $this->site = Site::factory()->create(['name' => 'Test Site']);
        $this->mixDesign = MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        $machineType = \App\Models\MachineType::firstOrCreate(
            ['name' => 'Concrete Pump'],
            ['code' => 'PUMP', 'status' => 'active', 'plant_id' => $this->plant->id]
        );
        $this->machine = \App\Models\Machine::factory()->create([
            'plant_id' => $this->plant->id,
            'vehicle_type' => $machineType->name,
        ]);

        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
            ['code' => 'SUPER_ADMIN']
        );
        $this->user->assignRole($role);

        \App\Models\EntityUser::create([
            'user_id' => $this->user->id,
            'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id,
            'role_id' => $role->id,
        ]);

        $this->withSession([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    public function test_can_list_sales_orders()
    {
        SalesOrder::factory(5)->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
        ]);

        $response = $this->get(route('salesorders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SalesOrders/Index')
            ->has('salesOrders', 5)
        );
    }

    public function test_can_create_sales_order()
    {
        $data = [
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
            'total_qty' => 100,
            'status' => SalesOrder::STATUS_SCHEDULED,
            'concrete_pump' => (string) $this->machine->id,
            'pump_rate' => 1500,
        ];

        $response = $this->post(route('salesorders.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_sales_orders', [
            'total_qty' => 100,
            'concrete_pump' => (string) $this->machine->id,
            'pump_rate' => 1500,
        ]);
    }

    public function test_can_show_sales_order()
    {
        $salesOrder = SalesOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
        ]);

        $response = $this->get(route('salesorders.show', $salesOrder));

        $response->assertStatus(200);
        $response->assertJsonPath('id', $salesOrder->id);
    }

    public function test_can_update_sales_order()
    {
        $salesOrder = SalesOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
            'total_qty' => 100,
            'status' => SalesOrder::STATUS_SCHEDULED,
        ]);

        $updateData = [
            'order_no' => $salesOrder->order_no,
            'prefix' => $salesOrder->prefix,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
            'total_qty' => 150,
            'status' => SalesOrder::STATUS_SCHEDULED,
            'concrete_pump' => (string) $this->machine->id,
            'pump_rate' => 2000,
        ];

        \App\Models\Batch::factory()->create(['plant_id' => $salesOrder->plant_id, 'sales_order_id' => $salesOrder->id]);
        $response = $this->put(route('salesorders.update', $salesOrder), $updateData);
        $response->assertSessionHasNoErrors();

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_sales_orders', [
            'id' => $salesOrder->id,
            'total_qty' => 150,
            'concrete_pump' => (string) $this->machine->id,
            'pump_rate' => 2000,
        ]);
    }

    public function test_admin_can_correct_a_batch_patron_but_cannot_delete_its_sales_order()
    {
        $order = SalesOrder::factory()->create(['plant_id' => $this->plant->id, 'customer_id' => $this->customer->id]);
        $batch = \App\Models\Batch::factory()->create(['plant_id' => $order->plant_id, 'sales_order_id' => $order->id]);
        $dispatch = \App\Models\Dispatch::factory()->create([
            'plant_id' => $order->plant_id, 'sales_order_id' => $order->id, 'batch_id' => $batch->id,
            'customer_id' => $this->customer->id, 'driver_id' => null,
        ]);
        $replacement = Patron::factory()->create(['plant_id' => $order->plant_id]);
        $this->put(route('batches.patron.update', $batch), ['customer_id' => $replacement->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($replacement->id, $dispatch->fresh()->customer_id);
        $this->assertEquals($replacement->legal_name, $batch->fresh()->patronName());
        $this->assertEquals($this->customer->id, $order->fresh()->customer_id);

        $dispatch->update(['dispatch_status' => 'Invoiced']);
        $this->put(route('batches.patron.update', $batch), ['customer_id' => $replacement->id])
            ->assertSessionHasErrors('customer_id');

        $this->delete(route('salesorders.destroy', $order))->assertSessionHasErrors('sales_order');
        $this->assertNull($order->fresh()->deleted_at);

        $operator = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'Batch Operator', 'code' => 'OPERATOR', 'guard_name' => 'web']);
        foreach (['SALES_ORDER.UPDATE', 'BATCH.UPDATE'] as $permission) {
            $role->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($permission, 'web'));
        }
        \App\Models\EntityUser::create(['user_id' => $operator->id, 'role_id' => $role->id,
            'plant_id' => $order->plant_id, 'entity_id' => $this->plant->entity_id]);
        $this->actingAs($operator);
        $this->put(route('batches.patron.update', $batch), ['customer_id' => $replacement->id])->assertForbidden();
        $this->put(route('salesorders.update', $order), [
            'order_no' => $order->order_no, 'customer_id' => $order->customer_id,
            'site_id' => $this->site->id, 'mix_design_id' => $this->mixDesign->id,
            'total_qty' => 123, 'status' => 2,
        ])->assertForbidden();
    }

    public function test_can_delete_sales_order()
    {
        $salesOrder = SalesOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $this->mixDesign->id,
        ]);

        $response = $this->delete(route('salesorders.destroy', $salesOrder));

        $response->assertRedirect();
        $this->assertSoftDeleted('mm_sales_orders', ['id' => $salesOrder->id]);
    }
}

