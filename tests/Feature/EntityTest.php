<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EntityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_entities()
    {
        $data = Entity::factory()->make()->getAttributes();
        
        $model = Entity::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_entities()
    {
        $model = Entity::factory()->create();
        $newData = Entity::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_entities()
    {
        $model = Entity::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Entity::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
