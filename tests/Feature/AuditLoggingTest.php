<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_create_update_and_soft_delete_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $entity = Entity::factory()->create([
            'legal_name' => 'Alpha Industries',
        ]);

        $createLog = ActivityLog::query()
            ->where('action_type', 'CREATE')
            ->where('entity_type', Entity::class)
            ->where('entity_id', (string) $entity->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertSame($user->id, $createLog->user_id);
        $this->assertSame('Alpha Industries', $createLog->new_values['legal_name']);

        $entity->update([
            'legal_name' => 'Beta Industries',
        ]);

        $updateLog = ActivityLog::query()
            ->where('action_type', 'UPDATE')
            ->where('entity_type', Entity::class)
            ->where('entity_id', (string) $entity->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame(['legal_name'], $updateLog->changed_fields);
        $this->assertSame('Alpha Industries', $updateLog->old_values['legal_name']);
        $this->assertSame('Beta Industries', $updateLog->new_values['legal_name']);

        $entity->delete();

        $deleteLog = ActivityLog::query()
            ->where('action_type', 'SOFT_DELETE')
            ->where('entity_type', Entity::class)
            ->where('entity_id', (string) $entity->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($deleteLog);
        $this->assertSame('Beta Industries', $deleteLog->old_values['legal_name']);
    }

    public function test_it_logs_login_and_logout_events(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        event(new Logout('web', $user));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action_type' => 'LOGIN',
            'module_name' => 'auth',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action_type' => 'LOGOUT',
            'module_name' => 'auth',
        ]);
    }
}
