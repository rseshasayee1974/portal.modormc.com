<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_currencies()
    {
        $data = Currency::factory()->make()->getAttributes();
        
        $model = Currency::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_currencies()
    {
        $model = Currency::factory()->create();
        $newData = Currency::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_currencies()
    {
        $model = Currency::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(Currency::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
