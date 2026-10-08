<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 10:57:21 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Invoice, Ledger, Patron, Payment, Plant};
use App\Services\Reports\CustomerOutstandingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class PatronStatementBillTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Patron $patron;
    private Ledger $ledger;
    private Invoice $bill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $this->patron = Patron::factory()->create(['plant_id' => $this->plant->id]);
        $this->ledger = Ledger::factory()->create(['plant_id' => $this->plant->id]);
        session(['active_plant_id' => $this->plant->id]);
        $this->bill = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
            'invoice_type' => 'Bill', 'invoice_label' => 'Manual', 'invoice_date' => '2026-10-07',
            'prefix' => 'Bill/26-27/', 'invoice_number' => '00004',
            'total_amount' => 1180, 'discount_total' => 100, 'status' => Invoice::STATUS_APPROVED,
        ]));
        $this->journal('opening_balance', 'OPENING', 25760);
        $this->journal('bill', 'BILL', 1180);
    }

    private function journal(string $module, string $type, float $credit): void
    {
        $entry = DB::table('mm_journal_entries')->insertGetId([
            'plant_id' => $this->plant->id, 'entity_id' => $this->plant->entity_id,
            'ref_module' => $module, 'ref_id' => $this->bill->id, 'voucher_type' => $type,
            'voucher_number' => $module . '-1', 'voucher_date' => '2026-10-07', 'posting_date' => '2026-10-07',
        ]);
        DB::table('mm_journal_entry_lines')->insert([
            'journal_entry_id' => $entry, 'plant_id' => $this->plant->id, 'account_id' => $this->ledger->id,
            'partner_type' => 'Patron', 'partner_id' => $this->patron->id, 'credit_amount' => $credit,
        ]);
    }

    private function statement(string $start = '2026-10-07'): array
    {
        return app(CustomerOutstandingReportService::class)->generateSinglePatronStatement(
            $this->patron->id, $this->plant->id, $start, '2026-10-31'
        );
    }

    public function test_notes_link_to_invoice_and_bill_and_post_correct_ledger_sides(): void
    {
        $this->assertNotesPosting();
    }

    public function test_notes_can_be_generated_without_purchase_history_column(): void
    {
        \Illuminate\Support\Facades\Schema::table('mm_invoice_items', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropIndex(['purchase_order_history_id']);
            $table->dropColumn('purchase_order_history_id');
        });
        $this->assertNotesPosting();
    }

    private function assertNotesPosting(): void
    {
        $base = Ledger::factory()->create(['plant_id' => $this->plant->id]);
        $tax = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_group' => 'GST', 'tax_rate' => 18, 'account_id' => $base->id]);
        foreach (['CGST', 'SGST'] as $name) {
            \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'parent_id' => $tax->id, 'tax_name' => $name, 'tax_rate' => 9, 'account_id' => $base->id]);
        }
        $this->patron->updateQuietly(['debit_ledger_id' => $this->ledger->id, 'credit_ledger_id' => $this->ledger->id]);
        foreach (['Invoice', 'Bill'] as $index => $documentType) {
            $source = Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id, 'account_id' => $base->id,
                'invoice_type' => $documentType, 'invoice_label' => 'Manual', 'prefix' => 'NOTE-SOURCE/',
                'invoice_number' => 100 + $index, 'invoice_date' => '2026-10-06', 'status' => Invoice::STATUS_APPROVED,
                'subtotal' => 1000, 'total_amount' => 1180, 'tax_amount' => 180, 'global_discount' => 0,
                'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0, 'paid_amount' => 0,
            ]);
            \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
                'invoice_id' => $source->id, 'subtotal' => 1000, 'quantity' => 1, 'price_unit' => 1000,
                'tax_id' => $tax->id, 'line_tax_amount' => 180, 'line_total' => 1180,
            ]));
            foreach (['credit_note', 'debit_note'] as $type) {
                $note = $source->generateAdjustmentNote($type, '2026-10-07', 'Adjustment test');
                $this->assertSame($source->id, (int) $note->ref_id);
                $this->assertSame($source->full_number, $note->ref_title);
                $this->assertSame($type, $note->invoice_type);
                $this->assertStringStartsWith($type === 'credit_note' ? 'CN/' : 'DN/', $note->full_number);
                $this->assertEquals(1180, $note->total_amount);
                $this->assertCount(1, $note->items);
                $this->assertCount(2, $note->orderTaxes);
                $this->assertEquals(180, $note->orderTaxes->sum('amount'));
                $this->assertSame($documentType === 'Bill' ? 'Purchase' : 'Invoice', $note->orderTaxes->first()->order_type);
                $entry = \App\Models\JournalEntry::where('ref_id', $note->id)->where('voucher_type', strtoupper($type))->firstOrFail();
                $this->assertEquals(1180, $entry->total_debit);
                $this->assertEquals($entry->total_debit, $entry->total_credit);
                $line = $entry->lines()->where('partner_id', $this->patron->id)->firstOrFail();
                $this->assertEquals($type === 'debit_note' ? 1180 : 0, $line->debit_amount);
                $this->assertEquals($type === 'credit_note' ? 1180 : 0, $line->credit_amount);
                $print = \App\Services\PrintDataFormatter::fromInvoice($note);
                $this->assertSame(strtoupper($note->invoice_label), $print['doc_title']);
                $this->assertSame($print['doc_title'], $print['settings']['pdf']['labels']['invoice_title']);
                $printer = new class extends \App\Http\Controllers\PrintController {
                    public function dataFor(string $module, string $id): array { return $this->resolveData($module, $id); }
                };
                foreach (['invoices', 'billings'] as $module) {
                    $this->assertSame(strtoupper($note->invoice_label), $printer->dataFor($module, $note->encrypted_id)['doc_title']);
                }
                $this->assertStringContainsString($source->full_number, $print['meta']['notes']);
                $this->assertSame($documentType === 'Bill', $print['is_purchase_bill']);
                $this->assertEquals(1180, $source->fresh()->total_amount);
                try {
                    $source->generateAdjustmentNote($type, '2026-10-07', 'Duplicate');
                    $this->fail('Duplicate full-value note must be rejected.');
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->assertArrayHasKey('note_type', $e->errors());
                }
            }
            $this->assertCount(2, $source->adjustmentNotes);
        }
        $gst = app(\App\Services\Reports\Gstr1ReportService::class)->generate(['start' => '2026-10-07', 'end' => '2026-10-07']);
        $this->assertCount(2, $gst['cdnr']);
        foreach ($gst['cdnr'] as $row) {
            $this->assertSame('2026-10-06', $row['original_inv_date']);
            $this->assertEquals(90, $row['cgst']);
            $this->assertEquals(90, $row['sgst']);
        }
    }

    public function test_note_generation_rejects_draft_sources_and_rolls_back_failed_posting(): void
    {
        $this->bill->updateQuietly(['status' => Invoice::STATUS_DRAFT]);
        try {
            $this->bill->generateAdjustmentNote('credit_note', '2026-10-07', 'Invalid');
            $this->fail('Draft source must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('note_type', $e->errors());
        }
        $this->bill->updateQuietly(['status' => Invoice::STATUS_APPROVED, 'account_id' => null]);
        try {
            $this->bill->generateAdjustmentNote('debit_note', '2026-10-07', 'Missing ledger');
            $this->fail('Missing accounting ledgers must reject posting.');
        } catch (\App\Exceptions\AccountingException $e) {
            $this->assertSame(0, $this->bill->adjustmentNotes()->count());
        }
    }

    public function test_note_endpoint_validates_date_and_restricts_active_plant(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->patron->updateQuietly(['credit_ledger_id' => $this->ledger->id]);
        $this->bill->updateQuietly(['account_id' => $this->ledger->id, 'subtotal' => 1180, 'total_amount' => 1180,
            'tax_amount' => 0, 'global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0]);
        $url = route('invoices.notes.store', $this->bill->encrypted_id);
        $data = ['note_type' => 'debit_note', 'note_date' => '2026-10-06', 'reason' => 'Returned goods'];
        $this->post($url, $data)->assertSessionHasErrors('note_date');
        $data['note_date'] = '2026-10-07';
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('debit_note', $this->bill->adjustmentNotes()->firstOrFail()->invoice_type);
        $otherPlant = Plant::factory()->create();
        $this->withSession(['active_plant_id' => $otherPlant->id, 'active_entity_id' => $otherPlant->entity_id]);
        $this->post($url, $data)->assertNotFound();
    }

    public function test_note_module_lists_creates_edits_and_reposts_without_changing_source(): void
    {
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->patron->updateQuietly(['credit_ledger_id' => $this->ledger->id]);
        $this->bill->updateQuietly(['account_id' => $this->ledger->id, 'subtotal' => 1180, 'total_amount' => 1180,
            'tax_amount' => 0, 'global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0]);
        $this->get(route('crdrnote.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('CrDrNote/Index')->has('documents', 1)->has('notes', 0));
        $this->post(route('crdrnote.store'), ['source_id' => $this->bill->id, 'note_type' => 'debit_note',
            'note_date' => '2026-10-07', 'reason' => 'Purchase Return / Bill Adjustment'])
            ->assertRedirect(route('crdrnote.index'))->assertSessionHasNoErrors();
        $note = $this->bill->adjustmentNotes()->firstOrFail();
        $number = $note->full_number;
        $entry = \App\Models\JournalEntry::where('ref_id', $note->id)->where('voucher_type', 'DEBIT_NOTE')->firstOrFail();
        $lines = $entry->lines()->count();
        $this->put(route('crdrnote.update', $note->encrypted_id), ['note_date' => '2026-10-08', 'reason' => 'Tax Correction'])
            ->assertRedirect(route('crdrnote.index'))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08', $note->fresh()->invoice_date->toDateString());
        $this->assertSame('Tax Correction', $note->fresh()->notes);
        $this->assertSame($number, $note->fresh()->full_number);
        $this->assertEquals(1180, $note->fresh()->total_amount);
        $this->assertSame('2026-10-08', $entry->fresh()->voucher_date->toDateString());
        $this->assertEquals($lines, $entry->lines()->count());
        $this->assertSame('2026-10-07', $this->bill->fresh()->invoice_date->toDateString());
        $this->put(route('crdrnote.update', $this->bill->encrypted_id), ['note_date' => '2026-10-08', 'reason' => 'Test'])->assertNotFound();
        $this->get(route('crdrnote.index'))->assertOk()->assertInertia(fn ($page) => $page->has('notes', 1));
        $other = Plant::factory()->create();
        $this->withSession(['active_plant_id' => $other->id, 'active_entity_id' => $other->entity_id]);
        $this->get(route('crdrnote.index'))->assertOk()->assertInertia(fn ($page) => $page->has('notes', 0)->has('documents', 0));
        $this->put(route('crdrnote.update', $note->encrypted_id), ['note_date' => '2026-10-08', 'reason' => 'Test'])->assertNotFound();
        $this->post(route('crdrnote.store'), ['source_id' => $this->bill->id, 'note_type' => 'credit_note',
            'note_date' => '2026-10-07', 'reason' => 'Test'])->assertNotFound();
    }

    public function test_note_module_requires_separate_view_create_update_permissions(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->get(route('crdrnote.index'))->assertForbidden();
        $this->post(route('crdrnote.store'), [])->assertForbidden();
        $this->put(route('crdrnote.update', $this->bill->encrypted_id), [])->assertForbidden();
        $user->givePermissionTo('CRDRNOTE.VIEW');
        $this->get(route('crdrnote.index'))->assertOk();
        $this->post(route('crdrnote.store'), [])->assertForbidden();
    }

    public function test_manual_invoice_and_bill_print_amounts_exclude_tax(): void
    {
        \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $this->bill->id, 'subtotal' => 1000, 'line_tax_amount' => 180, 'line_total' => 1180,
        ]));
        foreach (['Invoice', 'Bill'] as $type) {
            $this->bill->updateQuietly(['invoice_type' => $type, 'invoice_label' => 'Manual', 'subtotal' => 1000, 'tax_amount' => 180, 'total_amount' => 1180]);
            $data = \App\Services\PrintDataFormatter::fromInvoice($this->bill->fresh());
            $this->assertEquals(1000, $data['items'][0]['total']);
            $this->assertEquals(180, $data['items'][0]['tax_amount']);
            $this->assertEquals(1180, $this->bill->fresh()->total_amount);
        }
    }

    public function test_inclusive_gst_components_and_round_off_reconcile_to_selling_value(): void
    {
        $tax = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_group' => 'GST', 'tax_rate' => 18, 'account_id' => $this->ledger->id]);
        foreach (['CGST', 'SGST'] as $name) {
            \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'parent_id' => $tax->id, 'tax_name' => $name, 'tax_rate' => 9, 'account_id' => $this->ledger->id]);
        }
        $this->bill->updateQuietly(['is_tax_inclusive' => true, 'subtotal' => 1642.63, 'tax_amount' => 295.67, 'total_amount' => 1938.30, 'round_off' => -0.30, 'global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0]);
        $item = \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $this->bill->id, 'tax_id' => $tax->id, 'quantity' => 3.55, 'price_unit' => 546,
            'subtotal' => 1642.63, 'line_tax_amount' => 295.67, 'line_total' => 1938.30,
        ]));
        $this->bill->syncTaxSplits('Bill');
        $this->assertSame([147.84, 147.84], $this->bill->orderTaxes()->orderBy('id')->get()->map(fn ($tax) => (float) $tax->amount)->all());
        $this->assertEquals(295.68, $item->fresh()->line_tax_amount);
        $this->assertEquals(295.68, $this->bill->fresh()->tax_amount);
        $this->assertEquals(-0.31, $this->bill->fresh()->round_off);
        $item = $item->fresh();
        $item->setRelation('itemTaxes', $this->bill->orderTaxes()->get());
        $row = app(\App\Services\Reports\SalesRegisterService::class)->mapSalesRow($item);
        $this->assertEquals(295.68, $row['tax_amount']);
        $this->assertEquals($row['cgst'] + $row['sgst'], $row['tax_amount']);
        $this->assertEquals(1938, round($row['taxable_amount'] + $row['tax_amount'] + $row['roundoff'], 2));
        $this->bill->recalculate();
        $this->bill->syncTaxSplits('Bill');
        $this->assertEquals(1938, $this->bill->fresh()->total_amount);
        $this->assertEquals(-0.31, $this->bill->fresh()->round_off);
    }

    public function test_bill_recalculation_preserves_positive_negative_and_zero_round_off(): void
    {
        \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $this->bill->id, 'subtotal' => 1000.25,
            'discount_amount' => 0, 'line_tax_amount' => 180,
        ]));
        $this->bill->updateQuietly(['global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'paid_amount' => 100]);
        foreach ([[-0.25, 1180], [0.75, 1181], [0, 1180]] as [$roundOff, $total]) {
            $this->bill->updateQuietly(['round_off' => $roundOff]);
            $this->bill->recalculate();
            $this->bill->refresh();
            $this->assertEquals($total - 1180.25, $this->bill->round_off);
            $this->assertEquals($total, $this->bill->total_amount);
            $this->assertEquals($total - 100, $this->bill->balance_amount);
        }
        foreach ([\App\Http\Requests\StoreInvoiceRequest::class, \App\Http\Requests\UpdateInvoiceRequest::class] as $requestClass) {
            $rules = (new $requestClass)->rules();
            foreach ([-0.25, 0, 0.75] as $roundOff) {
                $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['round_off' => $roundOff], ['round_off' => $rules['round_off']])->passes());
            }
        }
    }

    public function test_invoice_and_bill_create_update_store_whole_rupee_totals(): void
    {
        foreach (['Invoice', 'Bill'] as $index => $type) {
            $document = Invoice::createWithItems([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
                'invoice_type' => $type, 'invoice_label' => 'Manual',
                'prefix' => 'ROUND/', 'invoice_number' => 100 + $index, 'invoice_date' => '2026-10-07',
                'status' => Invoice::STATUS_DRAFT, 'global_discount' => 0, 'paid_amount' => 100,
                'round_off' => 0, 'items' => [['quantity' => 1, 'price_unit' => 1938.30, 'item_name' => 'Test']],
            ])->fresh();
            $this->assertEquals(1938, $document->total_amount);
            $this->assertEquals(-0.30, $document->round_off);
            $this->assertEquals(1838, $document->balance_amount);
            $document->updateWithItems([
                'round_off' => 0,
                'items' => [['quantity' => 1, 'price_unit' => 1938.70, 'item_name' => 'Test']],
            ]);
            $document->refresh();
            $this->assertEquals(1939, $document->total_amount);
            $this->assertEquals(0.30, $document->round_off);
            $this->assertEquals(1839, $document->balance_amount);
            $this->assertDatabaseHas('mm_invoices', ['id' => $document->id, 'total_amount' => 1939]);
        }
    }

    public function test_partial_payment_balances_are_stored_as_whole_rupees(): void
    {
        \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $this->bill->id, 'subtotal' => 1938,
            'discount_amount' => 0, 'line_tax_amount' => 0,
        ]));
        $this->bill->updateQuietly(['global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0]);
        foreach (['Invoice', 'Bill'] as $type) {
            foreach ([[100.30, 1838], [100.70, 1837], [100.50, 1838], [1938, 0]] as [$paid, $balance]) {
                $this->bill->updateQuietly(['invoice_type' => $type, 'paid_amount' => $paid]);
                $this->bill->recalculate();
                $this->bill->syncTaxSplits($type);
                $this->assertDatabaseHas('mm_invoices', ['id' => $this->bill->id, 'balance_amount' => $balance]);
                $this->assertEquals($paid, $this->bill->fresh()->paid_amount);
            }
        }
        $this->bill->updateQuietly(['balance_amount' => 1837.70]);
        $this->assertEquals(1838, $this->bill->fresh()->balance_amount);
    }

    public function test_rounded_zero_balance_does_not_mark_a_partial_payment_as_fully_paid(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\TitleCaseInputs::class);
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->bill->updateQuietly(['total_amount' => 1180, 'paid_amount' => 1179.70, 'balance_amount' => 0.30, 'status' => Invoice::STATUS_APPROVED]);
        foreach ([[0.10, Invoice::STATUS_APPROVED], [0.20, Invoice::STATUS_PAID]] as $index => [$amount, $status]) {
            Payment::withoutEvents(fn () => $this->post(route('payments.store'), [
                'transaction_date' => '2026-10-07', 'ledger_id' => $this->ledger->id,
                'patron_id' => $this->patron->id, 'amount' => $amount, 'transaction_type' => 'payment',
                'status' => 'pending', 'reference' => 'ROUND-PAY-' . $index,
                'allocations' => [['invoice_id' => $this->bill->id, 'amount' => $amount]],
            ]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($status, $this->bill->fresh()->status);
            $this->assertEquals(0, $this->bill->fresh()->balance_amount);
        }
        $lastPayment = Payment::where('reference', 'ROUND-PAY-1')->firstOrFail();
        $this->delete(route('payments.destroy', $lastPayment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(Invoice::STATUS_APPROVED, $this->bill->fresh()->status);
        $this->assertEquals(1179.80, $this->bill->fresh()->paid_amount);
        $this->assertEquals(0, $this->bill->fresh()->balance_amount);
    }

    public function test_rounding_is_applied_to_document_total_after_all_items_are_saved(): void
    {
        $document = Invoice::createWithItems([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
            'invoice_type' => 'Invoice', 'invoice_label' => 'Manual', 'prefix' => 'ROUND/',
            'invoice_number' => 200, 'invoice_date' => '2026-10-07', 'global_discount' => 0,
            'round_off' => 0, 'items' => [
                ['quantity' => 1, 'price_unit' => 0.50, 'item_name' => 'First'],
                ['quantity' => 1, 'price_unit' => 0.50, 'item_name' => 'Second'],
            ],
        ])->fresh();
        $this->assertEquals(1, $document->total_amount);
        $this->assertEquals(0, $document->round_off);
    }

    public function test_bill_is_a_purchase_document_and_is_counted_once(): void
    {
        $report = $this->statement();
        $rows = collect($report['transactions']);
        $this->assertCount(2, $rows);
        $bill = $rows->firstWhere('transactions', 'Bill');
        $this->assertNotNull($bill);
        $this->assertSame('BILL', $bill['type']);
        $this->assertSame('BILL', $bill['voucher_type']);
        $this->assertSame('₹ 1,180.00', $bill['invoice_bill_display']);
        $this->assertSame('-', $bill['receipt_payment_display']);
        $this->assertEquals(-26940, $report['balance_due']);
        $this->assertEquals(1180, $report['account_summary']['purchased']);
        $this->assertEquals(0, $report['account_summary']['amount_paid']);
        $this->assertSame('-', $rows->first()['receipt_payment_display']);
    }

    public function test_ledger_filters_accept_canonical_and_legacy_invoice_bill_types(): void
    {
        $this->journal('manual', 'PURCHASE', 50);
        $this->journal('manual', 'INVOICE', 100);
        $this->journal('legacy', 'SALES', 200);
        $service = app(\App\Services\Reports\LedgerReportService::class);
        foreach (['BILL' => ['BILL', 'PURCHASE'], 'PURCHASE' => ['BILL', 'PURCHASE'], 'INVOICE' => ['INVOICE', 'SALES'], 'SALES' => ['INVOICE', 'SALES']] as $filter => $expected) {
            $report = $service->generate(['id' => $this->ledger->id, 'start' => '2026-10-07', 'end' => '2026-10-31', 'voucher_type_filter' => $filter]);
            $this->assertSame($expected, collect($report['transactions'])->pluck('voucher_type')->sort()->values()->all());
        }
    }

    public function test_ledger_report_returns_the_ledger_name_separately_from_narration(): void
    {
        $report = app(\App\Services\Reports\LedgerReportService::class)->generate([
            'start' => '2026-10-07',
            'end' => '2026-10-31',
        ]);

        $row = collect($report['transactions'])->firstWhere('voucher_type', 'BILL');

        $this->assertSame($this->ledger->title, $row['ledger_name']);
        $this->assertStringNotContainsString('[' . $this->ledger->title . ']', $row['narration']);
    }

    public function test_prior_bill_is_carried_into_opening_without_its_journal_being_counted_twice(): void
    {
        $report = $this->statement('2026-10-08');
        $this->assertCount(1, $report['transactions']);
        $this->assertEquals(-26940, $report['opening_balance']);
        $this->assertEquals(-26940, $report['balance_due']);
    }

    public function test_actual_payment_uses_payment_column_and_reduces_payable(): void
    {
        Payment::withoutEvents(fn () => Payment::factory()->create([
            'plant_id' => $this->plant->id, 'patron_id' => $this->patron->id, 'ledger_id' => $this->ledger->id,
            'transaction_date' => '2026-10-07', 'transaction_type' => 'payment', 'amount' => 500, 'status' => 'completed',
        ]));
        $report = $this->statement();
        $payment = collect($report['transactions'])->firstWhere('transactions', 'Payment Made');
        $this->assertSame('-', $payment['invoice_bill_display']);
        $this->assertSame('₹ 500.00', $payment['receipt_payment_display']);
        $this->assertEquals(500, $report['account_summary']['amount_paid']);
        $this->assertEquals(-26440, $report['balance_due']);
    }

    public function test_sales_invoice_and_receipt_keep_their_correct_columns(): void
    {
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
            'invoice_type' => 'Invoice', 'invoice_label' => 'Manual', 'invoice_date' => '2026-10-07',
            'total_amount' => 1000, 'status' => Invoice::STATUS_APPROVED,
        ]));
        Payment::withoutEvents(fn () => Payment::factory()->create([
            'plant_id' => $this->plant->id, 'patron_id' => $this->patron->id, 'ledger_id' => $this->ledger->id,
            'transaction_date' => '2026-10-07', 'transaction_type' => 'receipt', 'amount' => 300, 'status' => 'completed',
        ]));
        $report = $this->statement();
        $invoice = collect($report['transactions'])->firstWhere('transactions', 'Sales Invoice');
        $receipt = collect($report['transactions'])->firstWhere('transactions', 'Payment Received');
        $this->assertSame('₹ 1,000.00', $invoice['invoice_bill_display']);
        $this->assertSame('-', $invoice['receipt_payment_display']);
        $this->assertSame('-', $receipt['invoice_bill_display']);
        $this->assertSame('₹ 300.00', $receipt['receipt_payment_display']);
        $this->assertEquals(-26240, $report['balance_due']);
    }
}
