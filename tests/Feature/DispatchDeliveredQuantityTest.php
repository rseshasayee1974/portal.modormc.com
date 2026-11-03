<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 17:17:56 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Batch, CustomSetting, Dispatch, Invoice, InvoiceItem, Plant, SalesOrder, Tax, User};
use App\Services\DispatchDiscountCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class DispatchDeliveredQuantityTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Batch $batch;
    private Dispatch $dispatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $order = SalesOrder::factory()->create(['plant_id' => $this->plant->id, 'total_qty' => 100]);
        $this->batch = Batch::withoutEvents(fn () => Batch::factory()->create([
            'plant_id' => $this->plant->id, 'sales_order_id' => $order->id, 'batch_size' => 10,
        ]));
        $tax = Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_rate' => 18]);
        $this->dispatch = Dispatch::withoutEvents(fn () => Dispatch::create([
            'plant_id' => $this->plant->id, 'batch_id' => $this->batch->id, 'sales_order_id' => $order->id,
            'customer_id' => $order->customer_id, 'delivered_qty' => null, 'dispatch_status' => 'Draft',
            'load_rate' => 1000, 'load_tax_id' => $tax->id, 'discount_tax_mode' => 'after_tax',
            'load_untax_amount' => 10000, 'load_tax_amount' => 1800, 'load_total_amount' => 11800, 'round_off' => 0,
        ]));
    }

    private function basis(string $basis): void
    {
        CustomSetting::updateOrCreate(['plant_id' => $this->plant->id, 'module_name' => 'batching'],
            ['module_id' => 0, 'settings' => ['invoice_quantity_basis' => $basis, 'with_inventory' => false, 'weight_mandatory' => false]]);
    }

    public function test_blank_defaults_to_batch_size_but_zero_and_manual_values_are_preserved(): void
    {
        $this->assertEquals(10, $this->dispatch->delivered_qty);
        $this->assertNull($this->dispatch->delivered_qty_input);
        $this->dispatch->delivered_qty = 0;
        $this->assertEquals(0, $this->dispatch->delivered_qty);
        $this->dispatch->delivered_qty = 8;
        $this->assertEquals(10, $this->dispatch->invoiceQuantity());
        $this->basis('delivered_qty');
        $this->assertEquals(8, $this->dispatch->invoiceQuantity());
        $amounts = DispatchDiscountCalculator::forDispatch($this->dispatch->getAttributes(), false);
        $this->assertEquals(8000, $amounts['load_untax_amount']);
        $this->assertEquals(1440, $amounts['load_tax_amount']);
        $this->assertEquals(9440, $amounts['load_total_amount']);
        $this->basis('batch_size');
        $this->assertEquals(11800, DispatchDiscountCalculator::forDispatch($this->dispatch->getAttributes(), false)['load_total_amount']);
    }

    public function test_new_invoice_uses_selected_quantity_basis(): void
    {
        $this->mock(\App\Accounting\AccountingPostingService::class, fn ($mock) =>
            $mock->shouldReceive('post')->andReturn(new \App\Models\JournalEntry()));
        $this->dispatch->updateQuietly(['delivered_qty' => 8]);
        foreach (['batch_size' => [10, 11800], 'delivered_qty' => [8, 9440]] as $basis => [$qty, $amount]) {
            $this->basis($basis);
            $invoice = Invoice::createFromSource($this->dispatch->fresh(), 'invoice', ['plant_id' => $this->plant->id]);
            $this->assertEquals($qty, $invoice->items()->first()->quantity);
            $this->assertEquals($amount, $invoice->total_amount);
        }
    }

    public function test_quantity_totals_preserve_null_fallback_and_explicit_zero(): void
    {
        foreach ([null, 0, 8] as $quantity) {
            $this->dispatch->updateQuietly(['delivered_qty' => $quantity]);
            $total = Dispatch::query()->sum(DB::raw('COALESCE(mm_dispatches.delivered_qty, (SELECT batch_size FROM mm_batches WHERE mm_batches.id = mm_dispatches.batch_id), 0)'));
            $this->assertEquals($quantity ?? 10, $total);
            $total = DB::table('mm_dispatches as d')->leftJoin('mm_batches as b', 'b.id', '=', 'd.batch_id')
                ->sum(DB::raw('COALESCE(d.delivered_qty, b.batch_size, 0)'));
            $this->assertEquals($quantity ?? 10, $total);
        }
    }

    public function test_nullable_migration_preserves_explicit_zero_and_converts_automatic_quantity(): void
    {
        $migration = require database_path('migrations/2026_11_03_172830_use_nullable_delivered_qty_on_dispatches.php');
        $migration->down();
        DB::table('mm_dispatches')->where('id', $this->dispatch->id)
            ->update(['delivered_qty' => 0, 'delivered_qty_is_manual' => true]);
        $migration->up();
        $this->assertEquals(0, $this->dispatch->fresh()->getRawOriginal('delivered_qty'));
        $migration->down();
        DB::table('mm_dispatches')->where('id', $this->dispatch->id)
            ->update(['delivered_qty' => 10, 'delivered_qty_is_manual' => false]);
        $migration->up();
        $this->assertNull($this->dispatch->fresh()->getRawOriginal('delivered_qty'));
        $this->assertEquals(10, $this->dispatch->fresh()->delivered_qty);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('mm_dispatches', 'delivered_qty_is_manual'));
    }

    public function test_finalized_and_cancelled_quantity_corrections_do_not_change_financials(): void
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create(['plant_id' => $this->plant->id]));
        $item = InvoiceItem::withoutEvents(fn () => InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 10]));
        DB::table('mm_dispatch_statuses')->insert(['plant_id' => $this->plant->id, 'dispatch_id' => $this->dispatch->id,
            'invoice_id' => $invoice->id, 'invoice_status' => 1]);
        $invoiceBefore = $invoice->fresh()->getAttributes();
        $itemBefore = $item->fresh()->getAttributes();
        DB::table('mm_einvoice_invoice_rel')->insert(['invoice_id' => $invoice->id, 'plant_id' => $this->plant->id,
            'einv_ackno' => 'TEST-ACK', 'einv_ack_date' => now(), 'einv_irn' => 'TEST-IRN', 'einv_signed_invoice' => '{}',
            'einv_signed_qrcode' => 'test', 'einv_status' => 'ACT', 'created' => now()]);
        $einvoiceBefore = DB::table('mm_einvoice_invoice_rel')->where('invoice_id', $invoice->id)->first();
        foreach (['Invoiced', 'Cancelled'] as $state) {
            $this->dispatch->updateQuietly(['dispatch_status' => $state]);
            $before = $this->dispatch->fresh()->getAttributes();
            $this->patch(route('dispatches.delivered-quantity', $this->dispatch->id), [
                'delivered_qty' => 7.5, 'financials' => ['load_total_amount' => 1], 'dispatch_status' => 'Draft',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $updated = $this->dispatch->fresh();
            $this->assertEquals(7.5, $updated->delivered_qty);
            foreach (['load_untax_amount', 'load_tax_amount', 'load_total_amount', 'load_time', 'dispatch_status'] as $field) {
                $this->assertSame($before[$field] ?? null, $updated->getRawOriginal($field));
            }
            $this->assertSame($invoiceBefore, $invoice->fresh()->getAttributes());
            $this->assertSame($itemBefore, $item->fresh()->getAttributes());
            $this->assertEquals($einvoiceBefore, DB::table('mm_einvoice_invoice_rel')->where('invoice_id', $invoice->id)->first());
            $this->assertEquals(10, $this->batch->fresh()->batch_size);
        }
        $this->patch(route('dispatches.delivered-quantity', $this->dispatch->id), ['delivered_qty' => 0])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->dispatch->fresh()->delivered_qty);
        $this->patch(route('dispatches.delivered-quantity', $this->dispatch->id), ['delivered_qty' => null])->assertSessionHasNoErrors();
        $this->assertEquals(10, $this->dispatch->fresh()->delivered_qty);
        $this->patch(route('dispatches.delivered-quantity', $this->dispatch->id), ['delivered_qty' => -1])->assertSessionHasErrors('delivered_qty');
    }

    public function test_quantity_correction_requires_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->patchJson(route('dispatches.delivered-quantity', $this->dispatch->id), ['delivered_qty' => 5])->assertForbidden();
        $this->assertNull($this->dispatch->fresh()->delivered_qty_input);
    }

    public function test_batch_edits_preserve_manual_delivery_and_update_automatic_delivery(): void
    {
        $this->basis('delivered_qty');
        $this->dispatch->updateQuietly(['delivered_qty' => 8]);
        $payload = ['batch_no' => $this->batch->batch_no, 'batch_size' => 12,
            'sales_order_id' => $this->batch->sales_order_id, 'status' => Batch::STATUS_PLANNED,
            'empty_weight_truck' => 0, 'loaded_weight_truck' => 0, 'load_time' => now()->toDateTimeString(), 'materials' => []];
        $this->put(route('batches.update', $this->batch), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(8, $this->dispatch->fresh()->delivered_qty);
        $this->assertEquals(9440, $this->dispatch->fresh()->load_total_amount);
        $this->put(route('dispatches.update', $this->dispatch), ['payment_mode' => 'credit', 'delivered_qty' => null])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->dispatch->fresh()->delivered_qty_input);
        $this->assertNull($this->dispatch->fresh()->getRawOriginal('delivered_qty'));
        $this->assertEquals(12, $this->dispatch->fresh()->delivered_qty);
        $payload['batch_size'] = 14;
        $this->put(route('batches.update', $this->batch), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->dispatch->fresh()->getRawOriginal('delivered_qty'));
        $this->assertEquals(14, $this->dispatch->fresh()->delivered_qty);
        $this->assertEquals(16520, $this->dispatch->fresh()->load_total_amount);
    }
}
