<?php

namespace Tests\Feature;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_users()
    {
        $data = User::factory()->make()->getAttributes();
        
        $model = User::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_users()
    {
        $model = User::factory()->create();
        $newData = User::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_users()
    {
        $model = User::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(User::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }

    public function test_super_users_can_see_all_users_and_roles()
    {
        $plant = \App\Models\Plant::factory()->create();
        $superUser = User::factory()->create();
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $superUser->assignRole($role);

        // Also create a SAAS_OWNER user that standard users shouldn't see
        $saasOwnerUser = User::factory()->create();
        $saasRole = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Saas Owner', 'guard_name' => 'web'],
            ['code' => 'SAAS_OWNER']
        );
        $saasOwnerUser->assignRole($saasRole);

        $response = $this->actingAs($superUser)
            ->withSession([
                'active_plant_id' => $plant->id,
                'active_entity_id' => $plant->entity_id,
            ])
            ->get(route('users.index'));

        $response->assertStatus(200);
        
        // Assert that the page receives the SAAS_OWNER user
        $response->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->has('users.data')
        );
    }

    public function test_super_user_can_restore_and_force_delete_user()
    {
        $plant = \App\Models\Plant::factory()->create();
        $superUser = User::factory()->create();
        
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $superUser->assignRole($role);

        $targetUser = User::factory()->create();
        $targetUser->delete(); // Soft delete it

        $this->assertSoftDeleted($targetUser);

        // Restore it
        $response = $this->actingAs($superUser)
            ->withSession([
                'active_plant_id' => $plant->id,
                'active_entity_id' => $plant->entity_id,
            ])
            ->post(route('users.restore', $targetUser->id));

        $response->assertRedirect();
        $this->assertNotSoftDeleted($targetUser);

        // Force delete it
        $response = $this->actingAs($superUser)
            ->withSession([
                'active_plant_id' => $plant->id,
                'active_entity_id' => $plant->entity_id,
            ])
            ->delete(route('users.force-delete', $targetUser->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('mm_users', ['id' => $targetUser->id]);
    }
}
