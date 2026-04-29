<?php

namespace Tests\Feature;

use App\Models\InvoiceStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_invoice_statuses()
    {
        $data = InvoiceStatus::factory()->make()->getAttributes();
        
        $model = InvoiceStatus::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_invoice_statuses()
    {
        $model = InvoiceStatus::factory()->create();
        $newData = InvoiceStatus::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_invoice_statuses()
    {
        $model = InvoiceStatus::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(InvoiceStatus::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
