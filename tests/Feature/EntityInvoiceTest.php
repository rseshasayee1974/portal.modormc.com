<?php

namespace Tests\Feature;

use App\Models\EntityInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EntityInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_entity_invoices()
    {
        $data = EntityInvoice::factory()->make()->getAttributes();
        
        $model = EntityInvoice::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_entity_invoices()
    {
        $model = EntityInvoice::factory()->create();
        $newData = EntityInvoice::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_entity_invoices()
    {
        $model = EntityInvoice::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(EntityInvoice::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
