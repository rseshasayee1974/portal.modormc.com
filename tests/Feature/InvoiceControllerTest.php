<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Tax;
use App\Models\Ledger;
use App\Models\Invoice;
use App\Models\Dispatch;
use App\Models\ProductUnit;
use App\Models\MixDesign;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Plant $plant;
    private Patron $patron;
    private Ledger $account;
    private Tax $tax;
    private ProductUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Bypass authorization gates for testing
        Gate::before(fn () => true);

        // 2. Setup active user and plant
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->plant = Plant::factory()->create();
        session(['active_plant_id' => $this->plant->id]);

        // 3. Setup core entities required by store/update validation
        $this->patron = Patron::factory()->create([
            'legal_name' => 'Sundry Debtor Partner',
            'status' => true
        ]);

        $this->account = Ledger::factory()->create([
            'title' => 'Revenue Account',
            'plant_id' => $this->plant->id
        ]);

        $this->tax = Tax::factory()->create([
            'tax_name' => 'GST 18%',
            'tax_rate' => 18,
            'status' => 1
        ]);

        $this->unit = ProductUnit::factory()->create([
            'unit_code' => 'MT',
            'unit_name' => 'Metric Ton'
        ]);
    }

    /* =========================================================================
     * 1. INDEX METHOD TESTS (Best/Worst Cases)
     * ========================================================================= */

    public function test_index_displays_invoices_and_options_successfully()
    {
        Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'deleted_at' => null
        ]);

        $response = $this->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->has('invoices')
            ->has('patrons')
            ->has('taxes')
            ->has('accounts')
            ->has('next_invoice_number')
        );
    }

    public function test_index_ignores_soft_deleted_invoices()
    {
        $deletedInvoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'deleted_at' => now()
        ]);

        $response = $this->get(route('invoices.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('invoices', fn ($invoices) => collect($invoices)->pluck('id')->doesntContain($deletedInvoice->id))
        );
    }

    /* =========================================================================
     * 2. STORE METHOD TESTS (Best/Worst Cases)
     * ========================================================================= */

    public function test_store_creates_sales_invoice_with_items_and_accounting()
    {
        \Illuminate\Support\Facades\Cache::flush();

        $this->patron->update(['account_id' => $this->account->id]);

        \App\Models\AccountDefaultSetting::updateOrCreate(
            ['plant_id' => $this->plant->id, 'module_name' => 'Invoice', 'setting_key' => 'debit_ledger'],
            ['ledger_id' => $this->account->id, 'is_active' => true]
        );
        \App\Models\AccountDefaultSetting::updateOrCreate(
            ['plant_id' => $this->plant->id, 'module_name' => 'Invoice', 'setting_key' => 'shipping_account'],
            ['ledger_id' => $this->account->id, 'is_active' => true]
        );
        \App\Models\AccountDefaultSetting::updateOrCreate(
            ['plant_id' => $this->plant->id, 'module_name' => 'Invoice', 'setting_key' => 'discount_account'],
            ['ledger_id' => $this->account->id, 'is_active' => true]
        );
        \App\Models\AccountDefaultSetting::updateOrCreate(
            ['plant_id' => $this->plant->id, 'module_name' => 'Invoice', 'setting_key' => 'adjustment_account'],
            ['ledger_id' => $this->account->id, 'is_active' => true]
        );

        $payload = [
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'invoice_label' => 'Tax Invoice',
            'invoice_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'item_name' => 'Concrete Grade M25',
                    'hsn_code' => '3824',
                    'quantity' => 10,
                    'price_unit' => 4500,
                    'discount_type' => '%',
                    'discount' => 0,
                    'tax_id' => $this->tax->id,
                    'uom_id' => $this->unit->id,
                ]
            ]
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invoice = Invoice::latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->patron->id, $invoice->partner_id);
        $this->assertEquals($this->plant->id, $invoice->plant_id);
        $this->assertEquals(Invoice::STATUS_APPROVED, $invoice->status);
        $this->assertCount(1, $invoice->items);
    }

    public function test_store_fails_when_required_fields_are_missing()
    {
        // Missing partner_id, account_id, items
        $payload = [
            'invoice_type' => 'sales',
            'invoice_date' => now()->format('Y-m-d'),
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors(['partner_id', 'account_id', 'items']);
    }

    public function test_store_fails_when_item_quantity_is_invalid()
    {
        $payload = [
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'invoice_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'item_name' => 'Concrete Grade M25',
                    'quantity' => 0, // Invalid quantity < 0.01
                    'price_unit' => 4500,
                ]
            ]
        ];

        $response = $this->post(route('invoices.store'), $payload);

        $response->assertSessionHasErrors(['items.0.quantity']);
    }

    /* =========================================================================
     * 3. GET UNINVOICED DISPATCHES TESTS
     * ========================================================================= */

    public function test_get_uninvoiced_dispatches_returns_matching_records()
    {
        $dispatch = Dispatch::factory()->create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->patron->id,
            'dispatch_time' => now(),
        ]);

        \App\Models\DispatchStatus::create([
            'dispatch_id' => $dispatch->id,
            'plant_id' => $this->plant->id,
            'invoice_id' => null,
        ]);

        $response = $this->getJson(route('invoices.uninvoiced-dispatches', [
            'partner_id' => $this->patron->id,
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(1)->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $dispatch->id]);
    }

    public function test_get_uninvoiced_dispatches_validation_failure()
    {
        $response = $this->getJson(route('invoices.uninvoiced-dispatches', []));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['partner_id', 'start_date', 'end_date']);
    }

    /* =========================================================================
     * 4. UPDATE METHOD TESTS (Best/Worst Cases)
     * ========================================================================= */

    public function test_update_allows_editing_draft_invoice()
    {
        $invoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $payload = [
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'invoice_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'item_name' => 'Updated Item',
                    'hsn_code' => '3824',
                    'quantity' => 5,
                    'price_unit' => 2000,
                ]
            ]
        ];

        $response = $this->put(route('invoices.update', $invoice->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_update_blocks_editing_approved_invoice_without_status_change()
    {
        $invoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'status' => Invoice::STATUS_APPROVED,
        ]);

        $payload = [
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
            'invoice_type' => 'sales',
            'invoice_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'item_name' => 'Updated Item',
                    'hsn_code' => '3824',
                    'quantity' => 5,
                    'price_unit' => 2000,
                ]
            ]
        ];

        $response = $this->put(route('invoices.update', $invoice->id), $payload);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['error']);
    }

    /* =========================================================================
     * 5. SHOW METHOD TESTS
     * ========================================================================= */

    public function test_show_returns_invoice_details_json()
    {
        $invoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->getJson(route('invoices.show', $invoice->encrypted_id));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $invoice->id]);
    }

    public function test_show_fails_with_404_for_non_existent_invoice()
    {
        $response = $this->getJson(route('invoices.show', 999999));

        $response->assertStatus(404);
    }

    /* =========================================================================
     * 6. OUTSTANDING METHOD TESTS
     * ========================================================================= */

    public function test_outstanding_returns_only_approved_invoices_with_positive_balance()
    {
        $approvedWithBalance = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'status' => 'approved',
            'balance_amount' => 5000,
            'invoice_type' => 'sales'
        ]);

        $paidInvoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'status' => 'approved',
            'balance_amount' => 0,
            'invoice_type' => 'sales'
        ]);

        $response = $this->getJson(route('invoices.outstanding', [
            'partner_id' => $this->patron->id,
            'type' => 'sales'
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $approvedWithBalance->id]);
        $response->assertJsonMissing(['id' => $paidInvoice->id]);
    }

    /* =========================================================================
     * 7. DESTROY METHOD TESTS (Best/Worst Cases)
     * ========================================================================= */

    public function test_destroy_deletes_invoice_with_encrypted_or_raw_id()
    {
        $invoice = Invoice::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->patron->id,
            'account_id' => $this->account->id,
        ]);

        $response = $this->delete(route('invoices.destroy', $invoice->encrypted_id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('mm_invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_fails_for_non_existent_invoice()
    {
        $response = $this->delete(route('invoices.destroy', 'invalid-encrypted-id'));

        $response->assertStatus(404);
    }
}
