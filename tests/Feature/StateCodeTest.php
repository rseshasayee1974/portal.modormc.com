<?php

namespace Tests\Feature;

use App\Models\StateCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StateCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_state_codes()
    {
        $data = StateCode::factory()->make()->getAttributes();
        
        $model = StateCode::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_state_codes()
    {
        $model = StateCode::factory()->create();
        $newData = StateCode::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_state_codes()
    {
        $model = StateCode::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(StateCode::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
