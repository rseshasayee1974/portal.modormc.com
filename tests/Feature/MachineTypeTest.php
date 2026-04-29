<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\MachineType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MachineTypeTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Gate::before(fn () => true);
        
        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_list_machine_types()
    {
        MachineType::factory(2)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('machine-types.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MachineTypes/Index')
            ->has('machineTypes', 2)
        );
    }

    public function test_can_create_machine_type()
    {
        $response = $this->post(route('machine-types.store'), [
            'name' => 'Bulldozer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('machine_types', [
            'name' => 'Bulldozer',
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_can_soft_delete_machine_type()
    {
        $type = MachineType::factory()->create(['plant_id' => $this->plant->id]);

        $response = $this->delete(route('machine-types.destroy', $type->id));

        $response->assertRedirect();
        $this->assertSoftDeleted('machine_types', [
            'id' => $type->id,
        ]);
    }
}
