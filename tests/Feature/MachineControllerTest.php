<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MachineControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Gate::before(fn () => true);
    }

    public function test_machine_fleet_index_accessible()
    {
        $user = \App\Models\User::factory()->create();
        $entity = \App\Models\Entity::create(['legal_name' => 'Test Entity', 'entity_type' => 1]);
        $plant = \App\Models\Plant::factory()->create(['entity_id' => $entity->id]);
        $response = $this->actingAs($user)->withSession([
            'active_entity_id' => $entity->id,
            'active_plant_id' => $plant->id,
        ])->get(route('machines.index'));
        $response->assertStatus(200);
    }

    public function test_machine_can_be_enrolled_with_documents()
    {
        $user = \App\Models\User::factory()->create();
        $entity = \App\Models\Entity::create(['legal_name' => 'Test Entity', 'entity_type' => 1]);
        $plant = \App\Models\Plant::factory()->create(['entity_id' => $entity->id]);
        
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
        $this->assertDatabaseHas('mm_machines', ['registration' => 'UP 15 AH 9999']);
        $this->assertDatabaseHas('mm_machine_documents', ['type' => 'insurance', 'amount' => 12000]);
    }
}
