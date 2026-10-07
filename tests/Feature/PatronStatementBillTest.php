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
        $this->assertEquals(-0.01, $this->bill->fresh()->round_off);
        $item = $item->fresh();
        $item->setRelation('itemTaxes', $this->bill->orderTaxes()->get());
        $row = app(\App\Services\Reports\SalesRegisterService::class)->mapSalesRow($item);
        $this->assertEquals(295.68, $row['tax_amount']);
        $this->assertEquals($row['cgst'] + $row['sgst'], $row['tax_amount']);
        $this->assertEquals(1938.30, round($row['taxable_amount'] + $row['tax_amount'] + $row['roundoff'], 2));
        $this->bill->recalculate();
        $this->bill->syncTaxSplits('Bill');
        $this->assertEquals(1938.30, $this->bill->fresh()->total_amount);
        $this->assertEquals(-0.01, $this->bill->fresh()->round_off);
    }

    public function test_bill_recalculation_preserves_positive_negative_and_zero_round_off(): void
    {
        \App\Models\InvoiceItem::withoutEvents(fn () => \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $this->bill->id, 'subtotal' => 1000.25,
            'discount_amount' => 0, 'line_tax_amount' => 180,
        ]));
        $this->bill->updateQuietly(['global_discount' => 0, 'adjustment' => 0, 'shipping_charges' => 0, 'paid_amount' => 100]);
        foreach ([[-0.25, 1180], [0.75, 1181], [0, 1180.25]] as [$roundOff, $total]) {
            $this->bill->updateQuietly(['round_off' => $roundOff]);
            $this->bill->recalculate();
            $this->bill->refresh();
            $this->assertEquals($roundOff, $this->bill->round_off);
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
