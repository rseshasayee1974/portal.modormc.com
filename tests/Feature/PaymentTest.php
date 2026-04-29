<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Payment;
use App\Models\Ledger;
use App\Models\Patron;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        
        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_list_payments()
    {
        Payment::factory(3)->create(['plant_id' => $this->plant->id]);

        $response = $this->get(route('payments.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Payments/Index')
            ->has('payments', 3)
        );
    }

    public function test_can_create_payment()
    {
        $ledger = Ledger::factory()->create(['plant_id' => $this->plant->id]);
        $patron = Patron::factory()->create();

        $response = $this->post(route('payments.store'), [
            'ledger_id'        => $ledger->id,
            'patron_id'        => $patron->id,
            'amount'           => 1500.50,
            'transaction_type' => 'receipt',
            'description'      => 'Test payment',
            'reference'        => 'REF-123',
            'status'           => 'completed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('mm_payments', [
            'amount' => 1500.50,
            'transaction_type' => 'receipt',
            'status' => 'completed',
            'created_by' => $this->user->id
        ]);
    }

    public function test_cannot_delete_completed_payment()
    {
        $payment = Payment::factory()->create([
            'plant_id' => $this->plant->id,
            'status'   => 'completed'
        ]);

        $response = $this->delete(route('payments.destroy', $payment->id));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('mm_payments', [
            'id' => $payment->id
        ]);
    }

    public function test_can_delete_pending_payment()
    {
        $payment = Payment::factory()->create([
            'plant_id' => $this->plant->id,
            'status'   => 'pending'
        ]);

        $response = $this->delete(route('payments.destroy', $payment->id));

        $response->assertRedirect();
        $this->assertSoftDeleted($payment);
    }
}
