<?php

namespace Tests\Feature;

use App\Models\EntityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        EntityType::factory()->count(3)->create();

        $response = $this->get(route('entity-types.index'));

        $response->assertStatus(200);
        // Assuming Inertia is being used
        $response->assertInertia(fn ($page) => $page
            ->component('EntityTypes/Index')
            ->has('entityTypes', 3)
        );
    }

    public function test_can_store_entity_type()
    {
        $data = ['type' => 'Test Entity Type'];

        $response = $this->postJson(route('entity-types.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_entity_types', $data);
        $response->assertJsonStructure(['entityType', 'message']);
    }

    public function test_can_update_entity_type()
    {
        $entityType = EntityType::factory()->create(['type' => 'Old Type']);
        $newValue = 'New Type';

        $response = $this->putJson(route('entity-types.update', $entityType->id), ['type' => $newValue]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_entity_types', ['id' => $entityType->id, 'type' => $newValue]);
        $response->assertJsonStructure(['entityType', 'message']);
    }

    public function test_can_destroy_entity_type()
    {
        $entityType = EntityType::factory()->create();

        $response = $this->deleteJson(route('entity-types.destroy', $entityType->id));

        $response->assertStatus(200);
        // We use assertDatabaseMissing since soft deletes may or may not be active; better to check explicitly if it was wiped or use soft delete checks.
        // The original test checks if class uses soft deletes
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityType::class))) {
            $this->assertSoftDeleted($entityType);
        } else {
            $this->assertDatabaseMissing('mm_entity_types', ['id' => $entityType->id]);
        }
    }
}
