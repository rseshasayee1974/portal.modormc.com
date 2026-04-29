<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_plans()
    {
        $data = Plan::factory()->make()->toArray();
        
        $model = Plan::create($data);
        $this->assertDatabaseHas($model->getTable(), $model->getAttributes());
    }

    public function test_can_update_mm_plans()
    {
        $model = Plan::factory()->create();
        $newData = Plan::factory()->make()->toArray();

        $model->update($newData);
        $this->assertDatabaseHas($model->getTable(), $model->getAttributes());
    }

    public function test_can_delete_mm_plans()
    {
        $model = Plan::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Plan::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
