<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\EntityUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superUser;
    protected User $normalUser;
    protected Plant $plant;
    protected Plant $activeSessionPlant;
    protected Entity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superUser = User::factory()->create(['email_verified_at' => now()]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->superUser->assignRole($role);

        $this->normalUser = User::factory()->create(['email_verified_at' => now()]);
        $plainRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Operator', 'guard_name' => 'web'],
            ['code' => 'OPERATOR']
        );

        $entityType = EntityType::factory()->create();
        $this->entity = Entity::factory()->create(['entity_type' => $entityType->id]);
        $this->plant = Plant::factory()->create(['entity_id' => $this->entity->id]);
        
        // Create an additional active plant to hold the session context when the target plant is deleted
        $this->activeSessionPlant = Plant::factory()->create(['entity_id' => $this->entity->id]);

        // Create EntityUser relations to allow context switcher to bypass redirects
        EntityUser::create([
            'user_id' => $this->superUser->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'role_id' => $role->id,
        ]);
        EntityUser::create([
            'user_id' => $this->superUser->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->activeSessionPlant->id,
            'role_id' => $role->id,
        ]);

        EntityUser::create([
            'user_id' => $this->normalUser->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'role_id' => $plainRole->id,
        ]);
        EntityUser::create([
            'user_id' => $this->normalUser->id,
            'entity_id' => $this->entity->id,
            'plant_id' => $this->activeSessionPlant->id,
            'role_id' => $plainRole->id,
        ]);
    }

    public function test_super_user_can_restore_and_force_delete_plant()
    {
        $this->plant->delete();
        $this->assertSoftDeleted($this->plant);

        // Restore using the active session plant to bypass middleware redirect
        $response = $this->actingAs($this->superUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->post(route('plants.restore', $this->plant->id));

        $response->assertRedirect();
        
        // Refresh instance and verify
        $this->assertDatabaseHas('mm_plants', [
            'id' => $this->plant->id,
            'deleted_at' => null
        ]);

        // Force Delete
        $response = $this->actingAs($this->superUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->delete(route('plants.force-delete', $this->plant->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('mm_plants', ['id' => $this->plant->id]);
    }

    public function test_super_user_can_restore_and_force_delete_entity()
    {
        $this->entity->delete();
        $this->assertSoftDeleted($this->entity);

        // Restore
        $response = $this->actingAs($this->superUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->post(route('entities.restore', $this->entity->id));

        $response->assertRedirect();
        
        // Refresh instance and verify
        $this->assertDatabaseHas('mm_entities', [
            'id' => $this->entity->id,
            'deleted_at' => null
        ]);

        // Force Delete
        $response = $this->actingAs($this->superUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->delete(route('entities.force-delete', $this->entity->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('mm_entities', ['id' => $this->entity->id]);
    }

    public function test_non_super_user_cannot_restore_or_force_delete()
    {
        $this->plant->delete();

        // Try Restore Plant
        $response = $this->actingAs($this->normalUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->post(route('plants.restore', $this->plant->id));
            
        $this->assertTrue($response->status() === 403 || $response->isRedirect());
        $this->assertDatabaseHas('mm_plants', [
            'id' => $this->plant->id,
            'deleted_at' => $this->plant->deleted_at
        ]);

        // Try Force Delete Plant
        $response = $this->actingAs($this->normalUser)
            ->withSession([
                'active_plant_id' => $this->activeSessionPlant->id,
                'active_entity_id' => $this->entity->id,
            ])
            ->delete(route('plants.force-delete', $this->plant->id));
            
        $this->assertTrue($response->status() === 403 || $response->isRedirect());
        $this->assertDatabaseHas('mm_plants', [
            'id' => $this->plant->id,
            'deleted_at' => $this->plant->deleted_at
        ]);
    }
}
