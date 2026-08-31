<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Ledger;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $vendor;
    protected Currency $currency;
    protected ProductUnit $unit;
    protected Product $product;
    protected Tax $tax;
    protected Ledger $purchaseLedger;
    protected Ledger $sundryLedger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->vendor = Patron::factory()->create();
        $this->currency = Currency::factory()->create();
        $this->unit = ProductUnit::factory()->create();
        $this->product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);
        $this->tax = Tax::factory()->create(['tax_rate' => 18.00]);

        $entityId = $this->plant->entity_id;

        // Correctly initialize account and account type with entity_id to prevent constraint errors
        $this->account = \App\Models\Accounts::factory()->create([
            'plant_id' => $this->plant->id,
        ]);

        $accountsType = \App\Models\AccountsType::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_id' => $this->account->id,
        ]);

        // Seed default accounting ledgers for the plant context using the valid accountsType
        $this->sundryLedger = Ledger::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_type_id' => $accountsType->id,
            'title' => 'Sundry Creditors Ledger',
        ]);
        $this->purchaseLedger = Ledger::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_type_id' => $accountsType->id,
            'title' => 'Purchase Ledger',
        ]);
        // Other fallback ledgers
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'GST Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Shipping Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Adjustment Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Round Off Account']);

        // Set session active context
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);

        // Give test user the bypass role to pass AuthorizesModule gate
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->user->assignRole($role);

        $this->actingAs($this->user);
    }

    public function test_index_page_renders_with_inertia(): void
    {
        $response = $this->get(route('purchaseorder.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Index'));
    }

    public function test_create_page_renders_with_inertia(): void
    {
        $response = $this->get(route('purchaseorder.create'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Create'));
    }

    public function test_store_creates_purchase_order_and_items(): void
    {
        $data = [
            'vendor_id' => $this->vendor->id,
            'plant_id' => $this->plant->id,
            'date_order' => '2026-06-05',
            'discount_amount' => 10.00,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_uom' => $this->unit->id,
                    'tax_id' => $this->tax->id,
                    'product_quantity' => 10,
                    'unit_price' => 100,
                    'discount_type' => '%',
                    'discount_amount' => 0,
                ]
            ]
        ];

        $response = $this->post(route('purchaseorder.store'), $data);

        $response->assertRedirect(route('purchaseorder.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mm_purchase_orders', [
            'vendor_id' => $this->vendor->id,
            'plant_id' => $this->plant->id,
            'discount_amount' => 10.00,
        ]);

        $po = PurchaseOrder::where('vendor_id', $this->vendor->id)->first();
        $this->assertNotNull($po);
        $this->assertCount(1, $po->items);
    }

    public function test_show_returns_json_representation(): void
    {
        $po = PurchaseOrder::factory()->create(['plant_id' => $this->plant->id]);
        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        $response = $this->get(route('purchaseorder.show', $po->id));
        $response->assertStatus(200);
        $response->assertJsonPath('id', $po->id);
    }

    public function test_edit_page_renders_with_inertia(): void
    {
        $po = PurchaseOrder::factory()->create(['plant_id' => $this->plant->id]);
        
        $response = $this->get(route('purchaseorder.edit', $po->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('PurchaseOrders/Edit'));
    }

    public function test_update_modifies_purchase_order_and_items(): void
    {
        $po = PurchaseOrder::factory()->create(['plant_id' => $this->plant->id]);
        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'product_quantity' => 5,
            'unit_price' => 50,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        $updateData = [
            'vendor_id' => $po->vendor_id,
            'discount_amount' => 15.00,
            'items' => [
                [
                    'id' => $item->id,
                    'product_id' => $this->product->id,
                    'product_uom' => $this->unit->id,
                    'product_quantity' => 8, // updated
                    'unit_price' => 50,
                    'discount_type' => '%',
                    'discount_amount' => 0,
                ]
            ]
        ];

        $response = $this->put(route('purchaseorder.update', $po->id), $updateData);

        $response->assertRedirect(route('purchaseorder.index'));
        $this->assertDatabaseHas('mm_purchase_orders', [
            'id' => $po->id,
            'discount_amount' => 15.00,
        ]);

        $item->refresh();
        $this->assertEquals(8, $item->product_quantity);
    }

    public function test_update_fails_if_items_received(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'receipt_status' => 1, // items partially received
        ]);
        
        $response = $this->put(route('purchaseorder.update', $po->id), [
            'vendor_id' => $po->vendor_id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_uom' => $this->unit->id,
                    'product_quantity' => 10,
                    'unit_price' => 100,
                    'discount_type' => '%',
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Purchase Order cannot be modified as items have already been received.');
    }

    public function test_destroy_deletes_purchase_order(): void
    {
        $po = PurchaseOrder::factory()->create(['plant_id' => $this->plant->id]);
        
        $response = $this->delete(route('purchaseorder.destroy', $po->id));
        
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSoftDeleted($po);
    }

    public function test_destroy_fails_if_items_received(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'receipt_status' => 1,
        ]);

        $response = $this->delete(route('purchaseorder.destroy', $po->id));
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Purchase Order cannot be deleted as items have already been received.');
        $this->assertDatabaseHas('mm_purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
    }

    public function test_generate_bill_creates_accounting_entries_and_updates_status(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'exchange_rate' => 1.0,
        ]);
        
        PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'product_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        $this->withoutExceptionHandling();
        $response = $this->post(route('purchaseorder.generate-bill', $po->id), [
            'account_id' => $this->purchaseLedger->id,
            'invoice_date' => '2026-06-05',
        ]);
        // if (!session()->has('success')) {
        //     dd(session()->all());
        // }

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals(1, $po->invoice_status);
        $this->assertEquals('billed', $po->state);
        $this->assertNotNull($po->billing_id);

        // Assert Invoice was created in database
        $this->assertDatabaseHas('mm_invoices', [
            'id' => $po->billing_id,
            'invoice_type' => 'bill',
            'partner_id' => $this->vendor->id,
        ]);

        // Assert Journal Entry was posted
        $this->assertDatabaseHas('mm_journal_entries', [
            'ref_module' => 'bill',
            'ref_id' => $po->id,
            'is_status' => 'POSTED',
        ]);
    }

    public function test_concurrency_cannot_generate_bill_twice(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'exchange_rate' => 1.0,
        ]);
        
        PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'product_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        $this->withoutExceptionHandling();
        $response1 = $this->post(route('purchaseorder.generate-bill', $po->id), [
            'account_id' => $this->purchaseLedger->id,
            'invoice_date' => '2026-06-05',
        ]);
        if (!session()->has('success')) {
            // dd(session()->all());
        }
        $response1->assertSessionHas('success');

        // Second duplicate request (e.g. double click or concurrent request)
        $response2 = $this->post(route('purchaseorder.generate-bill', $po->id), [
            'account_id' => $this->purchaseLedger->id,
            'invoice_date' => '2026-06-05',
        ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('error');
    }

    public function test_delete_bill_reverts_po_and_deletes_invoice(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'exchange_rate' => 1.0,
        ]);
        
        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id,
            'plant_id' => $this->plant->id,
            'product_id' => $this->product->id,
            'product_uom' => $this->unit->id,
            'product_quantity' => 10,
            'received_quantity' => 10,
            'unit_price' => 100,
            'discount_type' => '%',
            'discount_amount' => 0,
        ]);

        // Generate Bill first
        $this->post(route('purchaseorder.generate-bill', $po->id), [
            'account_id' => $this->purchaseLedger->id,
            'invoice_date' => '2026-06-05',
        ]);

        $po->refresh();
        $billId = $po->billing_id;
        $this->assertNotNull($billId);

        // Delete (void) the bill
        $response = $this->delete(route('purchaseorder.delete-bill', $po->id));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals(0, $po->invoice_status);
        $this->assertEquals('approved', $po->state);
        $this->assertNull($po->billing_id);

        // Verify Invoice has been soft-deleted
        $this->assertSoftDeleted('mm_invoices', ['id' => $billId]);
    }
}
