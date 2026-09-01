<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\EntityUser;
use App\Models\Batch;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantDataCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant1;
    protected Plant $plant2;
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->user->assignRole($role);

        $entityType = EntityType::factory()->create();
        $this->entity = Entity::factory()->create(['entity_type' => $entityType->id]);
        $this->plant1 = Plant::factory()->create(['entity_id' => $this->entity->id]);
        $this->plant2 = Plant::factory()->create(['entity_id' => $this->entity->id]);

        EntityUser::create([
            'user_id' => $this->user->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant1->id,
            'role_id' => $role->id,
        ]);
        EntityUser::create([
            'user_id' => $this->user->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant2->id,
            'role_id' => $role->id,
        ]);
    }

    public function test_bulk_delete_only_affects_active_plant_records()
    {
        // Create batches for plant1 and plant2
        $batch1 = Batch::factory()->create(['plant_id' => $this->plant1->id, 'batch_no' => 'B-P1-001']);
        $batch2 = Batch::factory()->create(['plant_id' => $this->plant2->id, 'batch_no' => 'B-P2-001']);

        // Request bulk delete while session is plant1
        $response = $this->actingAs($this->user)
            ->withSession([
                'active_plant_id' => $this->plant1->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->post(route('plant-data-cleanup.bulk-delete'), [
                'module' => 'batches',
                'delete_all' => true,
                'force_delete' => false,
            ]);

        $response->assertRedirect();

        // Plant 1 batch should be soft deleted
        $this->assertSoftDeleted('mm_batches', ['id' => $batch1->id]);

        // Plant 2 batch MUST NOT be deleted
        $this->assertDatabaseHas('mm_batches', [
            'id' => $batch2->id,
            'deleted_at' => null,
        ]);
    }

    public function test_bulk_restore_only_restores_active_plant_records()
    {
        $batch1 = Batch::factory()->create(['plant_id' => $this->plant1->id, 'deleted_at' => now()]);
        $batch2 = Batch::factory()->create(['plant_id' => $this->plant2->id, 'deleted_at' => now()]);

        $response = $this->actingAs($this->user)
            ->withSession([
                'active_plant_id' => $this->plant1->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->post(route('plant-data-cleanup.bulk-restore'), [
                'module' => 'batches',
                'restore_all' => true,
            ]);

        $response->assertRedirect();

        // Plant 1 batch restored
        $this->assertDatabaseHas('mm_batches', [
            'id' => $batch1->id,
            'deleted_at' => null,
        ]);

        // Plant 2 batch remains soft deleted
        $this->assertSoftDeleted('mm_batches', ['id' => $batch2->id]);
    }
}
