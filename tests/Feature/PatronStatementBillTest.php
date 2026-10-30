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

    public function test_invoice_ewaybill_status_resolves_linked_batch_without_id_collision(): void
    {
        $invoice = $this->bill;
        $this->assertNull($invoice->fresh()->eway_bill_no);
        DB::table('mm_ewaybill_details')->insert([
            'plant_id' => $this->plant->id, 'generation_type' => 'batch',
            'origin_id' => $invoice->id, 'ewaybill_no' => 'UNRELATED-BATCH',
            'ewaybill_status' => 'ACT', 'status' => 1, 'created_at' => now(),
        ]);
        $this->assertNull($invoice->fresh()->eway_bill_no);
        $dispatch = \App\Models\Dispatch::withoutEvents(fn () => \App\Models\Dispatch::factory()->create(['plant_id' => $this->plant->id]));
        DB::table('mm_dispatch_statuses')->updateOrInsert(['dispatch_id' => $dispatch->id], [
            'plant_id' => $this->plant->id, 'invoice_id' => $invoice->id,
        ]);
        DB::table('mm_ewaybill_details')->where('generation_type', 'batch')->delete();
        DB::table('mm_ewaybill_details')->insert([
            'plant_id' => $this->plant->id, 'generation_type' => 'batch',
            'origin_id' => $dispatch->batch_id, 'ewaybill_no' => '123456789012',
            'ewaybill_date' => '2026-10-08 10:00:00', 'valid_upto' => '2026-10-09 23:59:59',
            'ewaybill_status' => 'ACT', 'status' => 1, 'created_at' => now(),
        ]);
        $data = $invoice->fresh()->toArray();
        $this->assertSame('123456789012', $data['eway_bill_no']);
        $this->assertSame('ACT', $data['ewaybill_detail']['ewaybill_status']);
        $this->assertSame('2026-10-09 23:59:59', $data['eway_bill_valid_until']);
        DB::table('mm_ewaybill_details')->insert([
            'plant_id' => $this->plant->id, 'generation_type' => 'invoice',
            'origin_id' => $invoice->id, 'ewaybill_no' => 'DIRECT-INVOICE',
            'ewaybill_status' => 'CNL', 'status' => 1, 'created_at' => now(),
        ]);
        $data = $invoice->fresh()->toArray();
        $this->assertSame('DIRECT-INVOICE', $data['eway_bill_no']);
        $this->assertSame('CNL', $data['ewaybill_detail']['ewaybill_status']);
    }

    private function statement(string $start = '2026-10-07'): array
    {
        return app(CustomerOutstandingReportService::class)->generateSinglePatronStatement(
            $this->patron->id, $this->plant->id, $start, '2026-10-31'
        );
    }

    public function test_sales_register_net_amounts_and_totals_are_whole_rupees(): void
    {
        $rows = [];
        foreach ([[1, 1938.30], [2, 1938.70], [3, 0.50], [3, 0.50]] as $index => [$document, $amount]) {
            $rows[] = ['id' => $index + 1, 'document_id' => $document, 'qty' => 1,
                'taxable_amount' => 10.25, 'tax_amount' => 1.85, 'net_amount' => $amount,
                'roundoff' => 0, 'discount' => 0, 'taxes' => ['CGST_9.00' => 0.92, 'SGST_9.00' => 0.93]];
        }
        $service = app(\App\Services\Reports\SalesRegisterService::class);
        $detail = $service->buildFromRows($rows, ['register_view' => 'detail']);
        $this->assertSame([1938.0, 1939.0, 1.0, 1.0], array_column($detail['data'], 'net_amount'));
        $this->assertEquals(3879, $detail['totals']['grand_total']);
        $summary = $service->buildFromRows($rows, ['register_view' => 'summary']);
        $this->assertSame([1938.0, 1939.0, 1.0], array_column($summary['data'], 'net_amount'));
        $this->assertEquals(3878, $summary['totals']['grand_total']);
        $paged = $service->buildFromRows($rows, ['register_view' => 'summary', 'page' => 2, 'per_page' => 1], false);
        $this->assertSame([1939.0], array_column($paged['data'], 'net_amount'));
        $this->assertEquals(3878, $paged['totals']['grand_total']);
        $this->assertEquals(10.25, $detail['data'][0]['taxable_amount']);
        $this->assertEquals(1.85, $detail['data'][0]['tax_amount']);
        $workbook = app(\App\Services\Reports\ExcelExportService::class)->generateExcelReport(
            'sales_register', '2026-10-01', '2026-10-08', $detail + ['excel_format' => 'complete']
        );
        $sheet = $workbook->getActiveSheet();
        foreach ($sheet->toArray() as $index => $cells) {
            $column = array_search('Net Amount', $cells, true);
            if ($column === false) continue;
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1);
            $this->assertEquals(1938, $sheet->getCell($letter . ($index + 2))->getValue());
            $this->assertEquals(1939, $sheet->getCell($letter . ($index + 3))->getValue());
            break;
        }
        $this->assertNotFalse($column);
        $workbook->disconnectWorksheets();
        $html = view('reports.register_pdf', ['title' => 'Sales Register', 'report' => $detail,
            'filters' => ['from_date' => '2026-10-01', 'to_date' => '2026-10-08'], 'generated_at' => '2026-10-08'])->render();
        $this->assertStringContainsString('1,938.00', $html);
        $this->assertStringContainsString('1,939.00', $html);
        $purchase = app(\App\Services\Reports\PurchaseRegisterService::class)->buildFromRows($rows, ['register_view' => 'detail']);
        $this->assertEquals(1938.30, $purchase['data'][0]['net_amount']);
    }

    public function test_registers_sort_document_number_then_date_ascending_and_keep_items_together(): void
    {
        session(['active_entity_id' => $this->plant->entity_id]);
        $user = \App\Models\User::factory()->create();
        $product = \App\Models\Product::factory()->create(['plant_id' => $this->plant->id]);
        $expectedSales = [];
        $expectedPurchase = [];
        foreach ([['00002', '2026-10-01'], ['00001', '2026-10-03'], ['00001', '2026-10-02'], ['00001', '2026-10-02']] as $index => [$number, $date]) {
            $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
                'invoice_type' => 'Invoice', 'invoice_number' => $number, 'invoice_date' => $date,
                'status' => 'Approved', 'is_active' => 1,
            ]));
            $salesItems = [];
            for ($itemIndex = 0; $itemIndex < 2; $itemIndex++) {
                $salesItems[] = \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create(['invoice_id' => $invoice->id]))->id;
            }
            $order = \App\Models\PurchaseOrder::withoutEvents(fn () => \App\Models\PurchaseOrder::factory()->create([
                'plant_id' => $this->plant->id, 'vendor_id' => $this->patron->id,
                'bill_number' => $number, 'billed_date' => $date, 'state' => 'purchase', 'created_by' => $user->id,
            ]));
            $purchaseItems = [];
            for ($itemIndex = 0; $itemIndex < 2; $itemIndex++) {
                $purchaseItems[] = \App\Models\PurchaseOrderItem::withoutEvents(fn () => \App\Models\PurchaseOrderItem::factory()->create([
                    'plant_id' => $this->plant->id, 'order_id' => $order->id, 'product_id' => $product->id,
                    'product_uom' => $product->unit_id, 'created_by' => $user->id,
                ]))->id;
            }
            $expectedSales[$index] = $salesItems;
            $expectedPurchase[$index] = $purchaseItems;
        }
        $filters = ['from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'plant_id' => $this->plant->id];
        $repository = app(\App\Repositories\ReportRepository::class);
        $this->assertSame(array_merge($expectedSales[2], $expectedSales[3], $expectedSales[1], $expectedSales[0]),
            $repository->getSalesRegisterQuery($filters)->pluck('mm_invoice_items.id')->all());
        $this->assertSame(array_merge($expectedPurchase[2], $expectedPurchase[3], $expectedPurchase[1], $expectedPurchase[0]),
            $repository->getPurchaseRegisterQuery($filters)->pluck('mm_purchase_order_items.id')->all());
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
            foreach ([$documentType === 'Invoice' ? 'credit_note' : 'debit_note'] as $noteIndex => $type) {
            $source = Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id, 'account_id' => $base->id,
                'invoice_type' => $documentType, 'invoice_label' => 'Manual', 'prefix' => 'NOTE-SOURCE/',
                'invoice_number' => 100 + $index * 2 + $noteIndex, 'invoice_date' => '2026-10-06', 'status' => Invoice::STATUS_APPROVED,
                'subtotal' => 1000, 'total_amount' => 1180, 'tax_amount' => 180, 'global_discount' => 0,
                'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0, 'paid_amount' => 0,
            ]);
            \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
                'invoice_id' => $source->id, 'subtotal' => 1000, 'quantity' => 1, 'price_unit' => 1000,
                'tax_id' => $tax->id, 'line_tax_amount' => 180, 'line_total' => 1180,
            ]));
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
                try {
                    $source->generateAdjustmentNote($type === 'credit_note' ? 'debit_note' : 'credit_note', '2026-10-07', 'Opposite note');
                    $this->fail('The opposite full-value note must be rejected.');
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->assertArrayHasKey('note_type', $e->errors());
                }
                $this->assertCount(1, $source->adjustmentNotes);
            }
        }
        $gst = app(\App\Services\Reports\Gstr1ReportService::class)->generate(['start' => '2026-10-07', 'end' => '2026-10-07']);
        $this->assertCount(1, $gst['cdnr']);
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

    public function test_note_type_must_match_source_document_type_including_legacy_aliases(): void
    {
        foreach (['Invoice', 'INVOICE', 'Sales', 'Bill', 'BILL', 'Purchase'] as $sourceType) {
            $this->bill->updateQuietly(['invoice_type' => $sourceType]);
            $wrongType = in_array(strtolower($sourceType), ['invoice', 'sales'], true) ? 'debit_note' : 'credit_note';
            try {
                $this->bill->generateAdjustmentNote($wrongType, '2026-10-07', 'Wrong source');
                $this->fail('A note cannot be generated for the wrong source type.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertArrayHasKey('note_type', $e->errors());
            }
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
        $this->delete(route('crdrnote.destroy', $this->bill->encrypted_id))->assertForbidden();
        $user->givePermissionTo('CRDRNOTE.VIEW');
        $this->get(route('crdrnote.index'))->assertOk();
        $this->post(route('crdrnote.store'), [])->assertForbidden();
    }

    public function test_note_items_and_tax_inclusive_can_be_created_and_edited_without_changing_source(): void
    {
        foreach (['Invoice', 'Purchase'] as $module) {
            \App\Models\AccountDefaultSetting::create(['plant_id' => $this->plant->id, 'entity_id' => $this->plant->entity_id,
                'module_name' => $module, 'setting_key' => 'round_off_account', 'ledger_id' => $this->ledger->id, 'is_active' => true]);
        }
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->patron->updateQuietly(['credit_ledger_id' => $this->ledger->id, 'debit_ledger_id' => $this->ledger->id]);
        $tax = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_group' => 'GST', 'tax_rate' => 18, 'parent_id' => null, 'account_id' => $this->ledger->id]);
        foreach (['CGST', 'SGST'] as $name) {
            \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'parent_id' => $tax->id, 'tax_name' => $name, 'tax_rate' => 9, 'account_id' => $this->ledger->id]);
        }
        foreach (['Invoice' => 'credit_note', 'Bill' => 'debit_note'] as $sourceType => $noteType) {
            $tax->updateQuietly(['tax_type' => $sourceType === 'Bill' ? 'purchase' : 'sales']);
            $oppositeTax = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id,
                'tax_type' => $sourceType === 'Bill' ? 'sales' : 'purchase', 'tax_group' => 'GST', 'tax_rate' => 18]);
            $source = Invoice::withoutEvents(fn () => Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id, 'account_id' => $this->ledger->id,
                'invoice_type' => $sourceType, 'invoice_date' => '2026-10-07', 'status' => 'Approved',
                'subtotal' => 1000, 'tax_amount' => 180, 'total_amount' => 1180, 'paid_amount' => 0,
                'global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'round_off' => 0,
            ]));
            $item = \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
                'invoice_id' => $source->id, 'tax_id' => $tax->id, 'quantity' => 1, 'price_unit' => 1000,
                'subtotal' => 1000, 'line_tax_amount' => 180, 'line_total' => 1180, 'discount_type' => '%', 'discount' => 0,
            ]));
            $catalogItem = $sourceType === 'Bill'
                ? \App\Models\Product::withoutEvents(fn () => \App\Models\Product::factory()->create(['plant_id' => $this->plant->id, 'product_type' => 'purchase']))
                : \App\Models\MixDesign::withoutEvents(fn () => \App\Models\MixDesign::create(['plant_id' => $this->plant->id,
                    'partner_id' => $this->patron->id, 'design_name' => 'Adjusted Design', 'design_code' => 'ADJ-TEST']));
            $line = ['id' => $item->id, 'item_id' => $catalogItem->id, 'item_name' => 'Adjusted product', 'uom_id' => $item->uom_id,
                'quantity' => 1, 'price_unit' => 118, 'tax_id' => $tax->id, 'discount_type' => '%', 'discount' => 0];
            $this->post(route('crdrnote.store'), ['source_id' => $source->id, 'note_type' => $noteType,
                'note_date' => '2026-10-07', 'invoice_number' => '00017', 'reason' => 'Rate / Price Adjustment', 'is_tax_inclusive' => true, 'items' => [$line]])
                ->assertRedirect()->assertSessionHasNoErrors();
            $note = $source->adjustmentNotes()->firstOrFail();
            $noteItem = $note->items()->firstOrFail();
            $this->assertSame('00017', $note->invoice_number);
            $this->assertTrue($note->is_tax_inclusive);
            $this->assertEquals(100, $note->subtotal);
            $this->assertEquals(18, $note->tax_amount);
            $this->assertEquals(118, $note->total_amount);
            $this->assertNotEquals($item->id, $noteItem->id);
            $this->assertSame('Adjusted Product', $noteItem->item_name);
            $this->assertSame($catalogItem->id, $noteItem->item_id);
            $this->assertNull($item->fresh()->item_id);
            $entry = \App\Models\JournalEntry::where('ref_id', $note->id)->where('voucher_type', strtoupper($noteType))->firstOrFail();
            $entryId = $entry->id;
            $line = array_merge($line, ['id' => $noteItem->id, 'quantity' => 2, 'price_unit' => 100, 'discount' => 10]);
            $update = ['note_date' => '2026-10-08', 'invoice_number' => '18', 'reason' => 'Discount Adjustment', 'is_tax_inclusive' => false, 'items' => [$line]];
            $this->put(route('crdrnote.update', $note->encrypted_id), $update)->assertRedirect(route('crdrnote.index'))->assertSessionHasNoErrors();
            $note = $note->fresh();
            $this->assertSame('00018', $note->invoice_number);
            Invoice::withoutEvents(fn () => Invoice::factory()->create(['plant_id' => $this->plant->id,
                'invoice_type' => $noteType, 'prefix' => $note->prefix, 'invoice_number' => '00019']));
            $this->put(route('crdrnote.update', $note->encrypted_id), array_merge($update, ['invoice_number' => '19']))
                ->assertSessionHasErrors('invoice_number');
            $this->assertFalse($note->is_tax_inclusive, json_encode($note->only(['is_tax_inclusive', 'subtotal', 'tax_amount', 'total_amount', 'notes'])));
            $this->assertEquals(180, $note->subtotal);
            $this->assertEquals(32.40, $note->tax_amount);
            $this->assertEquals(212, $note->total_amount);
            $this->assertEquals(212, $note->balance_amount);
            $this->assertEquals(-0.40, $note->round_off);
            $this->assertEquals($entry->fresh()->total_debit, $entry->fresh()->total_credit);
            $partnerLine = $entry->lines()->where('partner_id', $this->patron->id)->firstOrFail();
            $this->assertEquals(212, (float) $partnerLine->debit_amount + (float) $partnerLine->credit_amount);
            $this->assertSame($entryId, $entry->fresh()->id);
            $this->assertSame($note->full_number, $entry->fresh()->voucher_number);
            $this->assertSame(1, \App\Models\JournalEntry::where('ref_id', $note->id)->where('voucher_type', strtoupper($noteType))->count());
            $this->assertEquals(1180, $source->fresh()->total_amount);
            $this->assertEquals(1000, $item->fresh()->price_unit);
            $invalid = $update;
            $invalid['items'][0]['id'] = $item->id;
            $this->put(route('crdrnote.update', $note->encrypted_id), $invalid)->assertSessionHasErrors('items.0.id');
            $invalid = $update;
            $invalid['items'][0]['invoice_id'] = $source->id;
            $this->put(route('crdrnote.update', $note->encrypted_id), $invalid)->assertSessionHasErrors('items.0');
            $invalid = $update;
            $invalid['items'][0]['item_id'] = 999999;
            $this->put(route('crdrnote.update', $note->encrypted_id), $invalid)->assertSessionHasErrors('items.0.item_id');
            $invalid = $update;
            $invalid['items'][0]['tax_id'] = $oppositeTax->id;
            $this->put(route('crdrnote.update', $note->encrypted_id), $invalid)->assertSessionHasErrors('items.0.tax_id');
            $invalid = $update;
            $invalid['items'][0]['discount'] = 101;
            $this->put(route('crdrnote.update', $note->encrypted_id), $invalid)->assertSessionHasErrors('items.0.discount');
            $update['items'][0]['tax_id'] = null;
            $this->put(route('crdrnote.update', $note->encrypted_id), $update)->assertSessionHasNoErrors();
            $this->assertEquals(180, $note->fresh()->total_amount);
            $this->assertEquals(0, $note->fresh()->tax_amount);
            $this->assertCount(0, $note->fresh()->orderTaxes);
            $note->updateQuietly(['paid_amount' => 1]);
            $this->delete(route('crdrnote.destroy', $note->encrypted_id))->assertSessionHasErrors('delete');
            $this->assertFalse($note->fresh()->trashed());
            $this->assertFalse($entry->fresh()->trashed());
            $note->updateQuietly(['paid_amount' => 0]);
            $this->withSession(['active_plant_id' => $this->plant->id + 999]);
            $this->delete(route('crdrnote.destroy', $note->encrypted_id))->assertNotFound();
            $this->withSession(['active_plant_id' => $this->plant->id]);
            $this->delete(route('crdrnote.destroy', $source->encrypted_id))->assertNotFound();
            $noteNumber = $note->fresh()->full_number;
            $lineIds = $entry->lines()->pluck('id');
            $this->delete(route('crdrnote.destroy', $note->encrypted_id))->assertRedirect(route('crdrnote.index'))->assertSessionHasNoErrors();
            $this->assertSoftDeleted('mm_invoices', ['id' => $note->id]);
            $this->assertSoftDeleted('mm_invoice_items', ['id' => $noteItem->id]);
            $this->assertSoftDeleted('mm_journal_entries', ['id' => $entryId, 'is_deleted' => 1, 'deleted_by' => $user->id]);
            foreach ($lineIds as $lineId) {
                $this->assertSoftDeleted('mm_journal_entry_lines', ['id' => $lineId, 'is_deleted' => 1, 'deleted_by' => $user->id]);
            }
            $this->assertSame(0, $entry->lines()->count());
            $this->assertFalse($source->fresh()->trashed());
            $this->assertEquals(1180, $source->fresh()->total_amount);
            $this->assertSame(0, $source->adjustmentNotes()->count());
            $this->assertSame($noteNumber, Invoice::adjustmentNoteNumber($this->plant->id, $noteType, '18')['full_number']);
            $this->delete(route('crdrnote.destroy', $note->encrypted_id))->assertNotFound();
            $this->post(route('crdrnote.store'), ['source_id' => $source->id, 'note_type' => $noteType,
                'invoice_number' => '18', 'note_date' => '2026-10-08', 'reason' => 'Recreated Note'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $regenerated = $source->adjustmentNotes()->firstOrFail();
            $this->assertSame($noteNumber, $regenerated->full_number);
            $this->assertNotEquals($note->id, $regenerated->id);
            $this->assertSame(1, \App\Models\JournalEntry::where('ref_id', $regenerated->id)->where('voucher_type', strtoupper($noteType))->count());
            $taxIds = $regenerated->orderTaxes()->pluck('id');
            $this->assertNotEmpty($taxIds);
            $this->delete(route('crdrnote.destroy', $regenerated->encrypted_id))->assertSessionHasNoErrors();
            foreach ($taxIds as $taxId) {
                $this->assertSoftDeleted('mm_order_taxes', ['id' => $taxId]);
            }
        }
    }

    public function test_invoice_and_billing_tax_rules_use_the_module_for_create_and_edit(): void
    {
        $sales = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_type' => 'sales', 'tax_group' => 'GST']);
        $purchase = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_type' => 'purchase', 'tax_group' => 'IGST']);
        $inactive = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_type' => 'sales', 'tax_group' => 'GST', 'status' => 0]);
        $child = \App\Models\Tax::factory()->create(['plant_id' => $this->plant->id, 'tax_type' => 'sales', 'tax_group' => 'GST', 'parent_id' => $sales->id]);
        $foreign = \App\Models\Tax::factory()->create(['tax_type' => 'sales', 'tax_group' => 'GST']);
        foreach ([\App\Http\Requests\StoreInvoiceRequest::class, \App\Http\Requests\UpdateInvoiceRequest::class] as $requestClass) {
            foreach (['invoices' => $sales, 'billings' => $purchase] as $module => $validTax) {
                // The route determines tax direction even if a caller sends the opposite document type.
                $request = $requestClass::create('/', 'POST', ['invoice_type' => $module === 'invoices' ? 'Bill' : 'Invoice']);
                $route = new \Illuminate\Routing\Route('POST', '/', fn () => null);
                $route->name($module . ($requestClass === \App\Http\Requests\StoreInvoiceRequest::class ? '.store' : '.update'));
                $request->setRouteResolver(fn () => $route);
                $rules = $request->rules();
                foreach (['items.*.tax_id', 'shipping_tax_id'] as $field) {
                    $validate = fn ($id) => \Illuminate\Support\Facades\Validator::make(['tax' => $id], ['tax' => $rules[$field]])->passes();
                    $this->assertTrue($validate($validTax->id));
                    $this->assertTrue($validate(null));
                    foreach ([$module === 'invoices' ? $purchase : $sales, $inactive, $child, $foreign] as $invalidTax) {
                        $this->assertFalse($validate($invalidTax->id));
                    }
                }
            }
        }
    }

    public function test_note_numbers_use_fixed_prefixes_and_reject_only_non_deleted_plant_duplicates(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-08'));
        foreach (['credit_note' => 'CN/2627/', 'debit_note' => 'DN/2627/'] as $type => $prefix) {
            $details = Invoice::adjustmentNoteNumber($this->plant->id, $type, $prefix . '00042');
            $this->assertSame(['prefix' => $prefix, 'invoice_number' => '00042', 'full_number' => $prefix . '00042'], $details);
            $existing = Invoice::withoutEvents(fn () => Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'invoice_type' => $type, 'prefix' => $prefix, 'invoice_number' => '42', 'is_active' => 0,
            ]));
            try {
                Invoice::adjustmentNoteNumber($this->plant->id, $type, '00042');
                $this->fail('A non-deleted number must be reserved even when inactive.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                $this->assertArrayHasKey('invoice_number', $error->errors());
            }
            $this->assertSame('00042', Invoice::adjustmentNoteNumber($this->plant->id, $type, '42', $existing->id)['invoice_number']);
            DB::table('mm_invoices')->where('id', $existing->id)->update(['deleted_at' => now()]);
            $this->assertSame('00042', Invoice::adjustmentNoteNumber($this->plant->id, $type, '42')['invoice_number']);
            $otherPlant = Plant::factory()->create();
            Invoice::withoutEvents(fn () => Invoice::factory()->create([
                'plant_id' => $otherPlant->id, 'invoice_type' => $type, 'prefix' => $prefix, 'invoice_number' => '00042',
            ]));
            $this->assertSame('00042', Invoice::adjustmentNoteNumber($this->plant->id, $type, '42')['invoice_number']);
            $this->assertSame($prefix, Invoice::generateNumber($this->plant->id, $type, $this->ledger->id)['prefix']);
            try {
                Invoice::adjustmentNoteNumber($this->plant->id, $type, 'INV/2627/00042');
                $this->fail('An invoice prefix cannot be used for a note.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                $this->assertArrayHasKey('invoice_number', $error->errors());
            }
        }
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

    public function test_customer_outstanding_includes_notes_once_in_summary_statement_and_opening(): void
    {
        DB::table('mm_journal_entries')->where('ref_module', 'opening_balance')->update(['deleted_at' => now()]);
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id, 'invoice_type' => 'Invoice',
            'total_amount' => 3000, 'invoice_date' => '2026-10-07', 'due_date' => '2026-10-07', 'status' => 'Approved',
        ]));
        $makeNote = fn ($type, $amount, $date, $extra = []) => Invoice::withoutEvents(fn () => Invoice::factory()->create(array_merge([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id, 'invoice_type' => $type,
            'ref_id' => $type === 'credit_note' ? $invoice->id : $this->bill->id,
            'invoice_date' => $date, 'total_amount' => $amount, 'status' => 'Approved', 'discount_total' => 50,
        ], $extra)));
        $credit = $makeNote('credit_note', 200, '2026-10-08');
        $debit = $makeNote('debit_note', 100, '2026-10-09');
        $makeNote('credit_note', 900, '2026-10-08', ['status' => 'Draft']);
        $makeNote('debit_note', 900, '2026-10-08', ['status' => 'Cancelled']);
        $deleted = $makeNote('credit_note', 900, '2026-10-08');
        DB::table('mm_invoices')->where('id', $deleted->id)->update(['deleted_at' => now()]);
        $makeNote('credit_note', 900, '2026-11-01');
        $otherPlant = Plant::factory()->create();
        $makeNote('credit_note', 900, '2026-10-08', ['plant_id' => $otherPlant->id]);
        $service = app(CustomerOutstandingReportService::class);
        $summary = fn ($end = '2026-10-31') => $service->generate(['plant_id' => $this->plant->id, 'end' => $end]);
        $report = $summary();
        $customer = collect($report['customer_summary'])->firstWhere('customer_id', $this->patron->id);
        $this->assertEquals(1720, $customer['total_outstanding']);
        $this->assertEquals(200, $customer['total_credit_note']);
        $this->assertEquals(100, $customer['total_debit_note']);
        $this->assertEquals(1180, $customer['total_purchased']);
        $this->assertEquals(1720, array_sum(array_intersect_key($customer, array_flip(['aging_0_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus']))));
        $this->assertEquals(1720, collect($customer['open_invoices'])->sum('balance_amount'));
        $this->assertEquals(1620, $summary('2026-10-08')['total_outstanding_amount']);
        $statement = $this->statement();
        $this->assertEquals(1720, $statement['balance_due']);
        $creditRow = collect($statement['transactions'])->firstWhere('transactions', 'Credit Note');
        $debitRow = collect($statement['transactions'])->firstWhere('transactions', 'Debit Note');
        $this->assertEquals(200, $creditRow['credit']);
        $this->assertEquals(100, $debitRow['debit']);
        $this->assertSame('-', $creditRow['receipt_payment_display']);
        $this->assertSame('₹ 200.00', $creditRow['invoice_bill_display']);
        $this->assertSame('-', $creditRow['discount_display']);
        $this->assertEquals(200, $statement['account_summary']['credit_notes']);
        $this->assertEquals(100, $statement['account_summary']['debit_notes']);
        foreach ([$credit, $debit] as $note) {
            $entryId = DB::table('mm_journal_entries')->insertGetId([
                'plant_id' => $this->plant->id, 'entity_id' => $this->plant->entity_id,
                'ref_module' => $note->invoice_type === 'credit_note' ? 'credit_note' : 'purchase_debit_note',
                'ref_id' => $note->id, 'voucher_type' => strtoupper($note->invoice_type),
                'voucher_number' => $note->full_number, 'voucher_date' => $note->invoice_date, 'posting_date' => $note->invoice_date,
            ]);
            DB::table('mm_journal_entry_lines')->insert([
                'journal_entry_id' => $entryId, 'plant_id' => $this->plant->id, 'account_id' => $this->ledger->id,
                'partner_type' => 'Patron', 'partner_id' => $this->patron->id,
                'debit_amount' => $note->invoice_type === 'debit_note' ? 100 : 0,
                'credit_amount' => $note->invoice_type === 'credit_note' ? 200 : 0,
            ]);
        }
        $this->assertEquals(1720, $summary()['total_outstanding_amount']);
        $this->assertEquals(1720, $this->statement()['balance_due']);
        $this->assertCount(1, collect($this->statement()['transactions'])->where('transactions', 'Credit Note'));
        $openingReport = $this->statement('2026-10-09');
        $this->assertEquals(1620, $openingReport['opening_balance']);
        $this->assertEquals(1720, $openingReport['balance_due']);
        DB::table('mm_invoices')->where('id', $credit->id)->update(['deleted_at' => now()]);
        $this->assertEquals(1920, $summary()['total_outstanding_amount']);
        $this->assertEquals(1920, $this->statement()['balance_due']);
        $this->assertEquals(3000, $invoice->fresh()->total_amount);
        $this->assertEquals(1180, $this->bill->fresh()->total_amount);
        DB::table('mm_invoices')->where('id', $invoice->id)->update(['deleted_at' => now()]);
        $supplierStatement = $this->statement();
        $this->assertEquals(-1080, $supplierStatement['balance_due']);
        $this->assertSame('Cr', $supplierStatement['account_summary']['balance_due_type']);
    }

    public function test_outstanding_summary_matches_statement_for_the_5400_credit_note_example(): void
    {
        DB::table('mm_journal_entries')->where('ref_module', 'opening_balance')->update(['deleted_at' => now()]);
        DB::table('mm_invoices')->where('id', $this->bill->id)->update([
            'invoice_type' => 'Invoice', 'invoice_date' => '2026-09-30', 'due_date' => '2026-09-30',
            'total_amount' => 140780, 'discount_total' => 0, 'prefix' => 'INV/26-27/', 'invoice_number' => '00003',
        ]);
        $sourceId = null;
        foreach ([['00007', 5400.01], ['00010', 5400]] as [$number, $amount]) {
            $source = Invoice::withoutEvents(fn () => Invoice::factory()->create([
                'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
                'invoice_type' => 'Invoice', 'invoice_date' => '2026-10-07', 'due_date' => '2026-10-07',
                'invoice_number' => $number, 'prefix' => 'INV/26-27/', 'status' => 'Approved', 'discount_total' => 0,
            ]));
            // Preserve the historical paise value shown in the screenshot.
            DB::table('mm_invoices')->where('id', $source->id)->update(['total_amount' => $amount]);
            $sourceId = $source->id;
        }
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'plant_id' => $this->plant->id, 'partner_id' => $this->patron->id,
            'invoice_type' => 'credit_note', 'ref_id' => $sourceId, 'status' => 'Approved',
            'invoice_date' => '2026-10-08', 'total_amount' => 5400,
            'prefix' => 'CN/2627/', 'invoice_number' => '00001',
        ]));
        $service = app(CustomerOutstandingReportService::class);
        $params = ['plant_id' => $this->plant->id, 'start' => '2026-09-09 00:00:00', 'end' => '2026-10-09 23:59:59'];
        $summary = $service->generate($params);
        $statement = $service->generate($params + ['patron_id' => $this->patron->id]);
        $customer = collect($summary['transactions'])->firstWhere('customer_id', $this->patron->id);
        $this->assertEquals(151580.01, $customer['total_invoiced']);
        $this->assertEquals(5400, $customer['total_credit_note']);
        $this->assertEquals(5400, $summary['total_credit_note_amount']);
        $this->assertEquals(0, $summary['total_debit_note_amount']);
        $this->assertEquals(146180.01, $customer['total_outstanding']);
        $this->assertEquals(146180.01, $summary['total_outstanding_amount']);
        $this->assertEquals($customer['total_outstanding'], $statement['balance_due']);
        $this->assertEquals(146180.01, collect($customer['open_invoices'])->sum('balance_amount'));
        $this->assertEquals(146180.01, array_sum(array_intersect_key($customer, array_flip(['aging_0_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus']))));
        $html = view('reports.customer_outstanding_report', $summary)->render();
        $this->assertStringContainsString('Credit Notes', $html);
        $this->assertStringContainsString('Debit Notes', $html);
        $this->assertStringContainsString('146,180.01', $html);
        $this->assertStringContainsString('5,400.00', $html);
        $workbook = app(\App\Services\Reports\ExcelExportService::class)->generateExcelReport(
            'customer_outstanding', '2026-09-09', '2026-10-09', $summary
        );
        $sheet = $workbook->getActiveSheet();
        $found = false;
        foreach ($sheet->toArray() as $index => $cells) {
            $creditColumn = array_search('Credit Notes (₹)', $cells, true);
            if ($creditColumn === false) continue;
            $balanceColumn = array_search('Outstanding Balance (₹)', $cells, true);
            $this->assertNotFalse($balanceColumn);
            $this->assertEquals(5400, $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($creditColumn + 1) . ($index + 2))->getValue());
            $this->assertEquals(146180.01, $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($balanceColumn + 1) . ($index + 2))->getValue());
            $found = true;
            break;
        }
        $this->assertTrue($found);
        $workbook->disconnectWorksheets();
    }
}
