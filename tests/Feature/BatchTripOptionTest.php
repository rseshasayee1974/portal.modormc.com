<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 16:01:28 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Machine, Personnel, Plant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

class BatchTripOptionTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
    }

    public function test_truck_can_be_added_and_reused_from_dropdown(): void
    {
        $response = $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'tn 01-ab 1234'])
            ->assertOk()->assertJsonPath('option.registration', 'TN 01 AB 1234');
        $id = $response->json('option.id');
        $this->assertDatabaseHas('mm_machines', ['id' => $id, 'plant_id' => $this->plant->id,
            'entity_id' => $this->plant->entity_id, 'vehicle_type' => 'Truck', 'is_active' => 1, 'registration' => 'TN 01 AB 1234']);
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'TN01AB1234'])
            ->assertOk()->assertJsonPath('option.id', $id);
        $this->assertCount(1, MachinesDropdown(['Transit Mixer', 'Truck']));
    }

    public function test_compact_registration_is_formatted_and_legacy_truck_is_reused(): void
    {
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'tn00aa0000'])
            ->assertOk()->assertJsonPath('option.registration', 'TN 00 AA 0000');
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $this->plant->entity_id,
            'registration' => 'TN01AB5678', 'vehicle_type' => 'Truck', 'is_active' => true]);
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'tn 01 ab 5678'])
            ->assertOk()->assertJsonPath('option.id', $truck->id)->assertJsonPath('option.registration', 'TN 01 AB 5678');
        $this->assertSame('TN 01 AB 5678', $truck->fresh()->registration);
        foreach (['TN1AB1234', 'TN01A1234', 'TN01AB123', '1234567890'] as $invalid) {
            $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('name');
        }
    }

    public function test_driver_is_temporary_and_available_to_the_next_trip(): void
    {
        $response = $this->postJson(route('batches.trip-options.store'), ['kind' => 'driver', 'name' => 'Ravi Kumar'])
            ->assertOk()->assertJsonPath('option.label', 'Ravi Kumar');
        $id = $response->json('option.id');
        $this->assertDatabaseHas('mm_personnels', ['id' => $id, 'plant_id' => $this->plant->id,
            'employment_type' => 'temporary', 'status' => 'active']);
        $this->assertNotEmpty(Personnel::findOrFail($id)->employee_code);
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'driver', 'name' => ' ravi   kumar '])
            ->assertOk()->assertJsonPath('option.id', $id);
        $this->assertSame($id, DriversDropdown()->first()['id']);
    }

    public function test_another_plants_truck_is_not_reassigned(): void
    {
        $other = Plant::factory()->create(['entity_id' => $this->plant->entity_id]);
        $truck = Machine::factory()->create(['plant_id' => $other->id, 'entity_id' => $this->plant->entity_id,
            'registration' => 'TN01AB5678', 'vehicle_type' => 'Truck', 'is_active' => true]);
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'TN01AB5678'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('mm_machines', ['id' => $truck->id, 'plant_id' => $other->id]);
    }

    public function test_invalid_input_and_unauthorized_users_cannot_create_options(): void
    {
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => '<script>'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'driver', 'name' => ' '])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->actingAs(User::factory()->create());
        $this->postJson(route('batches.trip-options.store'), ['kind' => 'truck', 'name' => 'TN01AB9876'])->assertForbidden();
    }
}
