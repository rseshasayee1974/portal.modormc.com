<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Machine;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MachineControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
    }

    private function createEntityWithPlant()
    {
        $entityType = EntityType::factory()->create();
        $entity = Entity::factory()->create(['entity_type' => $entityType->id]);
        $plant = Plant::factory()->create(['entity_id' => $entity->id]);

        return [$entity, $plant];
    }

    public function test_machine_fleet_index_accessible()
    {
        $user = User::factory()->create();
        [$entity, $plant] = $this->createEntityWithPlant();

        $response = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity->id,
            'active_plant_id' => $plant->id,
        ])->get(route('machines.index'));

        $response->assertStatus(200);
    }

    public function test_machine_can_be_enrolled_with_documents_and_entity_id()
    {
        $user = User::factory()->create();
        [$entity, $plant] = $this->createEntityWithPlant();

        $response = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity->id,
            'active_plant_id' => $plant->id,
        ])->post(route('machines.store'), [
            'registration' => 'UP 15 AH 9999',
            'vehicle_type' => 'Truck',
            'make_year' => 2024,
            'documents' => [
                ['type' => 'insurance', 'amount' => 12000],
                ['type' => 'permit', 'amount' => 5000],
            ],
            'loans' => [
                [
                    'loan_amount' => 1000000,
                    'emi_amount' => 25000,
                    'tenure_months' => 48,
                    'start_date' => '2024-01-01',
                ]
            ]
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('mm_machines', [
            'registration' => 'UP 15 AH 9999',
            'plant_id' => $plant->id,
            'entity_id' => $entity->id,
        ]);
        $this->assertDatabaseHas('mm_machine_documents', ['type' => 'insurance', 'amount' => 12000]);
    }

    public function test_machine_can_be_updated_with_entity_id()
    {
        $user = User::factory()->create();
        [$entity, $plant] = $this->createEntityWithPlant();

        $machine = Machine::create([
            'registration' => 'UP 15 AH 1111',
            'vehicle_type' => 'Truck',
            'plant_id' => $plant->id,
            'entity_id' => $entity->id,
        ]);

        $response = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity->id,
            'active_plant_id' => $plant->id,
        ])->put(route('machines.update', $machine->id), [
            'registration' => 'UP 15 AH 2222',
            'vehicle_model' => 'Tata Prima',
            'vehicle_type' => 'Truck',
            'is_active' => true,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('mm_machines', [
            'id' => $machine->id,
            'registration' => 'UP 15 AH 2222',
            'vehicle_model' => 'Tata Prima',
            'entity_id' => $entity->id,
        ]);
    }

    public function test_machine_unique_registration_is_entity_id_scoped()
    {
        $user = User::factory()->create();
        [$entity1, $plant1] = $this->createEntityWithPlant();
        [$entity2, $plant2] = $this->createEntityWithPlant();

        // Machine in entity 1
        Machine::create([
            'registration' => 'KA 01 AB 1234',
            'vehicle_type' => 'Truck',
            'plant_id' => $plant1->id,
            'entity_id' => $entity1->id,
        ]);

        // Entity 2 should be allowed to have same registration
        $response = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity2->id,
            'active_plant_id' => $plant2->id,
        ])->post(route('machines.store'), [
            'registration' => 'KA 01 AB 1234',
            'vehicle_type' => 'Truck',
        ]);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Entity 1 should NOT be allowed to have duplicate registration
        $duplicateResponse = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity1->id,
            'active_plant_id' => $plant1->id,
        ])->post(route('machines.store'), [
            'registration' => 'KA 01 AB 1234',
            'vehicle_type' => 'Truck',
        ]);
        $duplicateResponse->assertSessionHasErrors('registration');
    }
}
