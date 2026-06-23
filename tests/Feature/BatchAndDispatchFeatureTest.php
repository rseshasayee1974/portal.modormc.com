<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\User;
use App\Models\SalesOrder;
use App\Models\Plant;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BatchAndDispatchFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $plant;
    protected $salesOrder;
    protected $batch;
    protected $ledger;
    protected $truck;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->salesOrder = SalesOrder::factory()->create(['plant_id' => $this->plant->id]);
        
        // Ensure user has active plant session
        session(['active_plant_id' => $this->plant->id]);
        
        $this->batch = Batch::factory()->create([
            'plant_id' => $this->plant->id,
            'sales_order_id' => $this->salesOrder->id,
            'status' => Batch::STATUS_PLANNED,
            'batch_size' => 10,
        ]);

        // Seed accounting ledgers context
        $account = \App\Models\Accounts::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        $accountsType = \App\Models\AccountsType::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $this->plant->entity_id,
            'account_id' => $account->id,
        ]);

        $this->ledger = \App\Models\Ledger::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $this->plant->entity_id,
            'account_type_id' => $accountsType->id,
            'title' => 'Sales Ledger',
        ]);

        $this->truck = \App\Models\Machine::factory()->create([
            'plant_id' => $this->plant->id,
        ]);
    }

    public function test_batch_index_page_loads_with_data()
    {
        $this->withoutExceptionHandling();
        $response = $this->actingAs($this->user)->get(route('batches.index'));

        $response->assertStatus(200);
        // Inertia asserts can be used if inertia testing package is installed, 
        // but basic 200 OK is a good start.
    }

    public function test_batch_show_returns_detailed_json()
    {
        $response = $this->actingAs($this->user)->get(route('batches.show', $this->batch->id));

        $response->assertStatus(200)
                 ->assertJsonPath('id', $this->batch->id)
                 ->assertJsonPath('status', Batch::STATUS_PLANNED);
    }

    public function test_batch_can_be_updated()
    {
        $payload = [
            'batch_no' => $this->batch->batch_no,
            'status' => Batch::STATUS_LOADING,
            'batch_size' => 12,
            'materials' => []
        ];

        $response = $this->actingAs($this->user)->put(route('batches.update', $this->batch->id), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mm_batches', [
            'id' => $this->batch->id,
            'status' => Batch::STATUS_LOADING,
            'batch_size' => 12
        ]);
    }

    public function test_dispatch_can_be_created_for_batch()
    {
        $this->withoutExceptionHandling();
        $this->batch->update(['status' => Batch::STATUS_DISPATCHED]);

        $payload = [
            'batch_id' => $this->batch->id,
            'delivered_qty' => 10,
            'dispatch_date' => now()->toDateString(),
            'dispatch_time' => now()->toDateTimeString(),
            'payment_mode' => 'cash',
            'truck_id' => $this->truck->id,
        ];

        $response = $this->actingAs($this->user)->post(route('dispatches.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mm_dispatches', [
            'batch_id' => $this->batch->id,
            'delivered_qty' => 10
        ]);
    }

    public function test_dispatch_can_be_updated()
    {
        $dispatch = Dispatch::factory()->create([
            'batch_id' => $this->batch->id,
            'delivered_qty' => 10,
            'dispatch_status' => 'Draft',
        ]);

        $payload = [
            'delivered_qty' => 8,
            'truck_id' => 1,
            'payment_mode' => 'cash',
        ];

        $response = $this->actingAs($this->user)->put(route('dispatches.update', $dispatch->id), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mm_dispatches', [
            'id' => $dispatch->id,
            'delivered_qty' => 8
        ]);
    }

    public function test_dispatch_invoice_can_be_generated()
    {
        $dispatch = Dispatch::factory()->create([
            'batch_id' => $this->batch->id,
            'delivered_qty' => 10
        ]);

        $response = $this->actingAs($this->user)->post(route('dispatches.generate-invoice', $dispatch->id), [
            'invoice_date' => now()->toDateString(),
            'ledger_id' => $this->ledger->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mm_dispatches', [
            'id' => $dispatch->id,
        ]);
    }

    public function test_batch_update_fails_validation_with_invalid_data()
    {
        $payload = [
            'status' => 'INVALID_STATUS',
            'batch_size' => -5, // Invalid size
        ];

        $response = $this->actingAs($this->user)->put(route('batches.update', $this->batch->id), $payload);

        $response->assertSessionHasErrors(['status', 'batch_size']);
    }

    public function test_dispatch_store_fails_validation_without_payment_mode()
    {
        $payload = [
            'batch_id' => $this->batch->id,
            'delivered_qty' => 10,
        ];

        $response = $this->actingAs($this->user)->post(route('dispatches.store'), $payload);

        $response->assertSessionHasErrors(['payment_mode']);
    }

    public function test_dispatch_cannot_be_created_for_nonexistent_batch()
    {
        $payload = [
            'batch_id' => 99999, // Non-existent batch
            'delivered_qty' => 10,
            'dispatch_date' => now()->toDateString(),
            'payment_mode' => 'cash',
        ];

        $response = $this->actingAs($this->user)->post(route('dispatches.store'), $payload);

        // Validation should fail or return 404 depending on implementation
        $response->assertSessionHasErrors(['batch_id']);
    }

    public function test_unauthenticated_user_cannot_access_batches()
    {
        $response = $this->get(route('batches.index'));
        $response->assertRedirect(route('login'));
    }
}
