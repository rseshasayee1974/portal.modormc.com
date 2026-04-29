<?php

namespace Tests\Feature;

use App\Models\EntityTax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EntityTaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_entity_taxes()
    {
        $data = EntityTax::factory()->make()->getAttributes();
        
        $model = EntityTax::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_entity_taxes()
    {
        $model = EntityTax::factory()->create();
        $newData = EntityTax::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_entity_taxes()
    {
        $model = EntityTax::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityTax::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
