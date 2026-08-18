<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\PumpRate;
use App\Models\Patron;
use App\Models\Machine;
use App\Models\Site;
use App\Models\ProductUnit;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumpRateTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Seed Platform Admin role to bypass permission gate via isSystemAdmin()
        $role = Role::create(['name' => 'Platform Admin', 'guard_name' => 'web', 'code' => 'PA']);
        $this->user->assignRole($role);
        
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_list_pump_rates()
    {
        $customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'patron_type' => 'Customer']);
        $pump = Machine::factory()->create(['plant_id' => $this->plant->id]);

        PumpRate::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1500,
            'rate_type' => 'Flat Rate',
            'name' => 'Test Pump Rate',
            'status' => true,
        ]);

        $response = $this->get(route('pumprates.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('PumpRates/Index')
            ->has('rates', 1)
        );
    }

    public function test_can_create_pump_rate()
    {
        $customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'patron_type' => 'Customer']);
        $pump = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $site = Site::factory()->create(['plant_id' => $this->plant->id]);
        $uom = ProductUnit::factory()->create();

        $response = $this->post(route('pumprates.store'), [
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1250.50,
            'rate_type' => 'Per UOM',
            'uom_id' => $uom->id,
            'name' => 'Hourly Pump Charge',
            'site_id' => $site->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_pump_rates', [
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1250.50,
            'rate_type' => 'Per UOM',
        ]);
    }

    public function test_can_create_global_pump_rate_without_customer()
    {
        $pump = Machine::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->post(route('pumprates.store'), [
            'customer_id' => null,
            'concrete_pump' => $pump->id,
            'rate' => 2000.00,
            'rate_type' => 'Flat Rate',
            'name' => 'Global Flat Rate Charge',
            'site_id' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_pump_rates', [
            'customer_id' => null,
            'concrete_pump' => $pump->id,
            'rate' => 2000.00,
            'rate_type' => 'Flat Rate',
        ]);
    }

    public function test_can_update_pump_rate()
    {
        $customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'patron_type' => 'Customer']);
        $pump = Machine::factory()->create(['plant_id' => $this->plant->id]);
        
        $rate = PumpRate::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1500,
            'rate_type' => 'Flat Rate',
            'name' => 'Initial Rate',
            'status' => true,
        ]);

        $response = $this->put(route('pumprates.update', $rate->id), [
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1750,
            'rate_type' => 'Flat Rate',
            'name' => 'Updated Rate',
            'status' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_pump_rates', [
            'id' => $rate->id,
            'rate' => 1750,
            'status' => false,
        ]);
    }

    public function test_can_delete_pump_rate()
    {
        $customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'patron_type' => 'Customer']);
        $pump = Machine::factory()->create(['plant_id' => $this->plant->id]);

        $rate = PumpRate::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $customer->id,
            'concrete_pump' => $pump->id,
            'rate' => 1500,
            'rate_type' => 'Flat Rate',
            'name' => 'To Delete',
            'status' => true,
        ]);

        $response = $this->delete(route('pumprates.destroy', $rate->id));

        $response->assertRedirect();
        $this->assertSoftDeleted($rate);
    }
}
