<?php

namespace Tests\Feature;

use App\Models\PaymentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_payment_statuses()
    {
        $data = PaymentStatus::factory()->make()->getAttributes();
        
        $model = PaymentStatus::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_payment_statuses()
    {
        $model = PaymentStatus::factory()->create();
        $newData = PaymentStatus::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_payment_statuses()
    {
        $model = PaymentStatus::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(PaymentStatus::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
