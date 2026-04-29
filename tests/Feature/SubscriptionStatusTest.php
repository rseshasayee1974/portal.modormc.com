<?php

namespace Tests\Feature;

use App\Models\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_subscription_statuses()
    {
        $data = SubscriptionStatus::factory()->make()->getAttributes();
        
        $model = SubscriptionStatus::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_subscription_statuses()
    {
        $model = SubscriptionStatus::factory()->create();
        $newData = SubscriptionStatus::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_subscription_statuses()
    {
        $model = SubscriptionStatus::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(SubscriptionStatus::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
