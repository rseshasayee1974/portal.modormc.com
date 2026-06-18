<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plant;
use App\Models\ConcreteGrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class ConcreteGradePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected ConcreteGrade $concreteGrade;

    protected function setUp(): void
    {
        if (isset($_ENV['DB_CONNECTION']) && $_ENV['DB_CONNECTION'] === 'mysql') {
            $this->markTestSkipped('Database migrations contain mysql-incompatible MySQL features.');
        }

        parent::setUp();

        $this->clearGateBeforeCallbacks();

        $this->plant = Plant::factory()->create();
        $this->concreteGrade = ConcreteGrade::create([
            'plant_id' => $this->plant->id,
            'name' => 'Test Grade M20',
            'status' => true,
        ]);

        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    protected function clearGateBeforeCallbacks()
    {
        $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
        $reflection = new \ReflectionClass($gate);
        $property = $reflection->getProperty('beforeCallbacks');
        $property->setAccessible(true);
        $property->setValue($gate, []);

        // Re-register Spatie's permission checking gate callback
        $gate->before(function ($user, $ability) {
            if (method_exists($user, 'hasPermissionTo')) {
                try {
                    return $user->hasPermissionTo($ability) ?: null;
                } catch (\Throwable $e) {
                    return null;
                }
            }
        });
    }

    public function test_platform_admin_can_delete_concrete_grade(): void
    {
        $admin = User::factory()->create([
            'default_plant_id' => $this->plant->id,
        ]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $admin->assignRole($role);

        $this->actingAs($admin);

        $this->assertTrue(Gate::allows('delete', $this->concreteGrade));
    }

    public function test_user_with_permission_and_same_plant_can_delete_concrete_grade(): void
    {
        $user = User::factory()->create([
            'default_plant_id' => $this->plant->id,
        ]);
        
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'CONCRETE_GRADE.delete', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);

        $this->assertTrue(Gate::allows('delete', $this->concreteGrade));
    }

    public function test_user_without_permission_cannot_delete_concrete_grade(): void
    {
        $user = User::factory()->create([
            'default_plant_id' => $this->plant->id,
        ]);

        $this->actingAs($user);

        $this->assertFalse(Gate::allows('delete', $this->concreteGrade));
    }

    public function test_user_cannot_delete_concrete_grade_from_different_plant(): void
    {
        $user = User::factory()->create([
            'default_plant_id' => $this->plant->id,
        ]);
        
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'CONCRETE_GRADE.delete', 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Define a grade belonging to a different plant
        $otherPlant = Plant::factory()->create();
        $otherGrade = ConcreteGrade::create([
            'plant_id' => $otherPlant->id,
            'name' => 'Other Plant Grade M30',
            'status' => true,
        ]);

        $this->actingAs($user);

        $this->assertFalse(Gate::allows('delete', $otherGrade));
    }
}
