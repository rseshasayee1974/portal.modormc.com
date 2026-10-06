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
        $response->assertSessionHas('error', 'Purchase Order cannot be deleted as items have already been received or inwarded.');
        $this->assertDatabaseHas('mm_purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
    }

    public function test_generate_bill_creates_accounting_entries_and_updates_status(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'exchange_rate' => 1.0,
            'state' => 'approved',
            'receipt_status' => 2,
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
            'invoice_type' => 'Bill',
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
            'state' => 'approved',
            'receipt_status' => 2,
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
            'state' => 'approved',
            'receipt_status' => 2,
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
        $response = $this->delete(route('purchaseorder.delete-bill', $po->id), ['bill_id' => $billId]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals(0, $po->invoice_status);
        $this->assertEquals('approved', $po->state);
        $this->assertNull($po->billing_id);

        // Verify Invoice has been soft-deleted
        $this->assertSoftDeleted('mm_invoices', ['id' => $billId]);
    }
    public function test_partial_receipts_generate_separate_bills_and_void_only_one_quantity(): void
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id, 'vendor_id' => $this->vendor->id,
            'state' => 'approved', 'receipt_status' => 1,
            'discount_amount' => 0, 'shipping_charges' => 0, 'adjustment' => 0,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id, 'plant_id' => $this->plant->id,
            'product_id' => $this->product->id, 'product_uom' => $this->unit->id,
            'product_quantity' => 1000, 'received_quantity' => 10, 'invoiced_quantity' => 0,
            'unit_price' => 100, 'discount_type' => '%', 'discount_amount' => 0, 'tax_id' => null,
        ]);
        $payload = ['account_id' => $this->purchaseLedger->id, 'invoice_date' => '2026-09-26'];
        $this->post(route('purchaseorder.generate-bill', $po), $payload)->assertSessionHas('success');
        $first = $po->bills()->firstOrFail();
        $this->assertEquals(10, $first->items()->first()->quantity);
        $this->assertEquals(1000, $first->total_amount);
        $this->assertEquals('approved', $po->fresh()->state);
        $item->update(['received_quantity' => 30]);
        $this->post(route('purchaseorder.generate-bill', $po), $payload)->assertSessionHas('success');
        $second = $po->bills()->latest('id')->firstOrFail();
        $this->assertEquals(20, $second->items()->first()->quantity);
        $this->assertEquals(2000, $second->total_amount);
        $this->assertEquals(30, $item->fresh()->invoiced_quantity);
        $this->post(route('purchaseorder.generate-bill', $po), $payload)->assertSessionHas('error');
        $this->assertEquals(2, $po->bills()->count());
        $this->delete(route('purchaseorder.delete-bill', $po), ['bill_id' => $first->id])->assertSessionHas('success');
        $this->assertEquals(20, $item->fresh()->invoiced_quantity);
        $this->assertEquals(1, $po->bills()->count());
        $this->assertEquals(1, $po->fresh()->invoice_status);
    }

    private function inwardForBilling(): \App\Models\PurchaseOrderHistory
    {
        $po = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id, 'vendor_id' => $this->vendor->id,
            'state' => 'approved', 'receipt_status' => 1,
            'discount_amount' => 0, 'shipping_charges' => 0, 'adjustment' => 0,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'order_id' => $po->id, 'plant_id' => $this->plant->id,
            'product_id' => $this->product->id, 'product_uom' => $this->unit->id,
            'product_quantity' => 100, 'received_quantity' => 10, 'invoiced_quantity' => 0,
            'unit_price' => 100, 'discount_type' => '%', 'discount_amount' => 0, 'tax_id' => null,
        ]);
        \App\Models\Quantity::create([
            'plant_id' => $this->plant->id, 'product_id' => $this->product->id,
            'uom_id' => $this->unit->id, 'quantity' => 10, 'opening_quantity' => 0, 'status' => 1,
        ]);

        return \App\Models\PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id, 'order_id' => $po->id, 'order_item_id' => $item->id,
            'product_id' => $this->product->id, 'uom_id' => $this->unit->id,
            'received_date' => '2026-10-06', 'inward_no' => 'INW-BILL',
            'received_qty' => 10, 'truck_loaded' => 10, 'truck_empty' => 0, 'status' => 1,
        ]);
    }

    private function inwardBillPayload(): array
    {
        return ['generate_bill' => true, 'bill' => [
            'account_id' => $this->purchaseLedger->id, 'invoice_date' => '2026-10-06', 'unit_price' => 125,
        ]];
    }

    public function test_inward_save_bills_updated_quantity_and_prevents_a_second_bill(): void
    {
        $inward = $this->inwardForBilling();
        $unit = \App\Models\ProductUnit::factory()->create();
        $payload = $this->inwardBillPayload() + ['truck_empty' => 3, 'conversion_quantity' => 2, 'conversion_uom_id' => $unit->id];
        $this->post(route('inwards.update-weight', $inward), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $inward->refresh();
        $bill = $inward->order->bills()->firstOrFail();
        $line = $bill->items()->firstOrFail();
        $this->assertEquals(7, $inward->received_qty);
        $this->assertEquals(7, $inward->item->invoiced_quantity);
        $this->assertEquals(2, $line->quantity);
        $this->assertEquals($unit->id, $line->uom_id);
        $this->assertEquals(125, $line->price_unit);
        $this->assertEquals(250, $bill->total_amount);
        $this->assertEquals($inward->id, $line->purchase_order_history_id);
        $this->assertDatabaseHas('mm_quantity', ['product_id' => $this->product->id, 'quantity' => 7]);
        $this->post(route('inwards.update-weight', $inward), $this->inwardBillPayload())->assertSessionHasErrors('bill.inward_ids');
        $this->assertEquals(1, $inward->order->bills()->count());
        $this->delete(route('purchaseorder.delete-bill', $inward->order), ['bill_id' => $bill->id])->assertSessionHas('success');
        $this->assertEquals(0, $inward->item->fresh()->invoiced_quantity);
    }

    public function test_inward_without_conversion_bills_received_quantity_and_unit(): void
    {
        $inward = $this->inwardForBilling();
        $inward->update(['uom_id' => ProductUnit::factory()->create()->id]);
        $this->post(route('inwards.update-weight', $inward), $this->inwardBillPayload())->assertSessionHasNoErrors();
        $bill = $inward->order->bills()->firstOrFail();
        $line = $bill->items()->firstOrFail();
        $this->assertEquals(10, $line->quantity);
        $this->assertEquals($inward->uom_id, $line->uom_id);
        $this->assertEquals(1250, $line->line_total);
        $this->delete(route('purchaseorder.delete-bill', $inward->order), ['bill_id' => $bill->id])->assertSessionHas('success');
        $this->assertEquals(0, $inward->item->fresh()->invoiced_quantity);
    }

    public function test_converted_inward_bill_requires_conversion_unit_and_rolls_back(): void
    {
        $inward = $this->inwardForBilling();
        $this->post(route('inwards.update-weight', $inward), $this->inwardBillPayload() + [
            'truck_empty' => 3, 'conversion_quantity' => 2,
        ])->assertSessionHasErrors('bill.items');
        $this->assertEquals(10, $inward->fresh()->received_qty);
        $this->assertEquals(0, $inward->item->fresh()->invoiced_quantity);
        $this->assertEquals(0, $inward->order->bills()->count());
    }

    public function test_invalid_inward_bill_rolls_back_weight_stock_and_units(): void
    {
        $inward = $this->inwardForBilling();
        $payload = $this->inwardBillPayload() + ['truck_empty' => 3, 'conversion_quantity' => 2];
        $payload['bill']['account_id'] = null;
        $this->post(route('inwards.update-weight', $inward), $payload)->assertSessionHasErrors('bill.account_id');
        $this->assertEquals(0, $inward->fresh()->truck_empty);
        $this->assertEquals(10, $inward->fresh()->received_qty);
        $this->assertEquals(10, $inward->item->fresh()->received_quantity);
        $this->assertDatabaseHas('mm_quantity', ['product_id' => $this->product->id, 'quantity' => 10]);
        $this->assertEquals(0, $inward->order->bills()->count());
    }

    public function test_inward_bill_requires_loaded_weight_and_valid_rate(): void
    {
        $inward = $this->inwardForBilling();
        $inward->update(['truck_loaded' => 0]);
        $this->post(route('inwards.update-weight', $inward), $this->inwardBillPayload())->assertSessionHasErrors('bill');
        $inward->update(['truck_loaded' => 10]);
        $payload = $this->inwardBillPayload();
        $payload['bill']['unit_price'] = -1;
        $this->post(route('inwards.update-weight', $inward), $payload)->assertSessionHasErrors('bill.unit_price');
        $this->assertEquals(0, $inward->order->bills()->count());
    }

    public function test_inward_bill_cannot_select_another_receipt(): void
    {
        $inward = $this->inwardForBilling();
        $other = $inward->replicate();
        $other->inward_no = 'INW-OTHER';
        $other->save();
        $inward->item->update(['received_quantity' => 20]);
        $payload = $this->inwardBillPayload();
        $payload['bill']['inward_ids'] = [$other->id];
        $this->post(route('inwards.update-weight', $inward), $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
        $bill = $inward->order->bills()->firstOrFail();
        $this->assertCount(1, $bill->items);
        $this->assertEquals($inward->id, $bill->items->first()->purchase_order_history_id);
        $this->assertEquals(10, $bill->items->first()->quantity);
    }
}
