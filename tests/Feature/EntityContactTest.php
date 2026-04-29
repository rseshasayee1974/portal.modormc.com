<?php

namespace Tests\Feature;

use App\Models\EntityContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EntityContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_entity_contacts()
    {
        $data = EntityContact::factory()->make()->getAttributes();
        
        $model = EntityContact::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_entity_contacts()
    {
        $model = EntityContact::factory()->create();
        $newData = EntityContact::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_entity_contacts()
    {
        $model = EntityContact::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityContact::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
