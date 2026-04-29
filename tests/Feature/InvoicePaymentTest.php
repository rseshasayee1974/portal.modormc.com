<?php

namespace Tests\Feature;

use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_mm_invoice_payments()
    {
        $data = InvoicePayment::factory()->make()->getAttributes();
        
        $model = InvoicePayment::create($data);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_update_mm_invoice_payments()
    {
        $model = InvoicePayment::factory()->create();
        $newData = InvoicePayment::factory()->make()->getAttributes();

        $model->update($newData);
        $this->assertModelMatchesDatabase($model);
    }

    public function test_can_delete_mm_invoice_payments()
    {
        $model = InvoicePayment::factory()->create();

        $model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(InvoicePayment::class))) {
            $this->assertSoftDeleted($model);
        } else {
            $this->assertDatabaseMissing($model->getTable(), ['id' => $model->id]);
        }
    }
}
