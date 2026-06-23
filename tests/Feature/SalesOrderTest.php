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

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create(['name' => 'Main Plant']);
        session(['active_plant_id' => $this->plant->id]);

        $this->customer = Patron::factory()->create(['legal_name' => 'Test Customer']);
        $this->site = Site::factory()->create(['name' => 'Test Site']);
        $this->mixDesign = MixDesign::factory()->create(['plant_id' => $this->plant->id]);

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
        ];

        $response = $this->post(route('salesorders.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_sales_orders', ['total_qty' => 100]);
    }
}
