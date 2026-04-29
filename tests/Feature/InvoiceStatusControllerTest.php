<?php

namespace Tests\Feature;

use App\Models\InvoiceStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_can_view_index()
    {
        InvoiceStatus::factory()->count(3)->create();

        $response = $this->get(route('invoice-statuses.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('InvoiceStatuses/Index')
            ->has('invoiceStatuses', 3)
        );
    }

    public function test_can_store_invoice_status()
    {
        $data = [
            'status_name' => 'Paid'
        ];

        $response = $this->postJson(route('invoice-statuses.store'), $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_invoice_statuses', $data);
        $response->assertJsonStructure(['invoiceStatus', 'message']);
    }

    public function test_can_update_invoice_status()
    {
        $invoiceStatus = InvoiceStatus::factory()->create([
            'status_name' => 'Pending'
        ]);
        
        $newData = [
            'status_name' => 'Overdue'
        ];

        $response = $this->putJson(route('invoice-statuses.update', $invoiceStatus->id), $newData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mm_invoice_statuses', array_merge(['id' => $invoiceStatus->id], $newData));
        $response->assertJsonStructure(['invoiceStatus', 'message']);
    }

    public function test_can_destroy_invoice_status()
    {
        $invoiceStatus = InvoiceStatus::factory()->create();

        $response = $this->deleteJson(route('invoice-statuses.destroy', $invoiceStatus->id));

        $response->assertStatus(200);
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(InvoiceStatus::class))) {
            $this->assertSoftDeleted($invoiceStatus);
        } else {
            $this->assertDatabaseMissing('mm_invoice_statuses', ['id' => $invoiceStatus->id]);
        }
    }
}
