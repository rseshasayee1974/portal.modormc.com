<?php

namespace Tests\Feature;

use App\Models\Personnel;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $entity;
    protected $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->entity = Entity::factory()->create();
        $this->plant = Plant::factory()->create(['entity_id' => $this->entity->id]);
        
        session(['active_entity_id' => $this->entity->id]);
        session(['active_plant_id' => $this->plant->id]);
        
        $this->actingAs($this->user);
    }

    public function test_can_view_personnel_index()
    {
        $response = $this->get(route('personnel.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Personnel/Index'));
    }

    public function test_can_create_personnel()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'employee_type' => 'Permanent',
            'gender' => 'Male',
            'status' => 'active',
            'contacts' => [
                ['contact_type' => 'Phone', 'contact_value' => '1234567890', 'is_primary' => true]
            ]
        ];

        $response = $this->post(route('personnel.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_personnels', ['first_name' => 'John']);
        $this->assertDatabaseHas('mm_personnel_contacts', ['contact_value' => '1234567890']);
    }

    public function test_can_update_personnel()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'inactive',
        ];

        $response = $this->put(route('personnel.update', $personnel->id), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_personnels', [
            'id' => $personnel->id,
            'first_name' => 'Jane',
            'status' => 'inactive'
        ]);
    }

    public function test_can_delete_personnel()
    {
        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
        ]);

        $response = $this->delete(route('personnel.destroy', $personnel->id));

        $response->assertRedirect();
        $this->assertSoftDeleted('mm_personnels', ['id' => $personnel->id]);
    }
}
