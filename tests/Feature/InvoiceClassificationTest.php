<?php

namespace Tests\Feature;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\TestCase;

class InvoiceClassificationTest extends TestCase
{
    public function test_journal_voucher_assignment_normalizes_legacy_values(): void
    {
        foreach (['Sales' => 'INVOICE', 'INVOICE' => 'INVOICE', ' purchase ' => 'BILL', 'Bill' => 'BILL', 'PAYMENT' => 'PAYMENT', 'RECEIPT' => 'RECEIPT', 'JV' => 'JV'] as $input => $expected) {
            $entry = new \App\Models\JournalEntry(['voucher_type' => $input]);
            $this->assertSame($expected, $entry->getAttributes()['voucher_type']);
        }
        $this->assertSame('INVOICE', \App\Accounting\DocumentTypeConfig::get('invoice')['voucher_type']);
        $this->assertSame('BILL', \App\Accounting\DocumentTypeConfig::get('bill')['voucher_type']);
    }

    public function test_journal_migration_changes_only_voucher_type_including_deleted_entries(): void
    {
        DB::statement('CREATE TABLE mm_journal_entries (id INTEGER PRIMARY KEY, voucher_type TEXT, voucher_number TEXT, total_credit INTEGER, deleted_at TEXT)');
        try {
            foreach (['Sales', ' purchase ', 'INVOICE', 'Bill', 'PAYMENT', 'RECEIPT', 'OPENING'] as $index => $type) {
                DB::table('mm_journal_entries')->insert(['id' => $index + 1, 'voucher_type' => $type, 'voucher_number' => 'DOC-' . $index, 'total_credit' => 1180, 'deleted_at' => $index === 0 ? '2026-10-01' : null]);
            }
            $before = DB::table('mm_journal_entries')->orderBy('id')->get()->map(fn ($row) => [$row->voucher_number, $row->total_credit, $row->deleted_at])->all();
            $migration = require database_path('migrations/2026_10_07_130000_normalize_journal_voucher_types.php');
            $migration->up();
            $this->assertSame(['INVOICE', 'BILL', 'INVOICE', 'BILL', 'PAYMENT', 'RECEIPT', 'OPENING'], DB::table('mm_journal_entries')->orderBy('id')->pluck('voucher_type')->all());
            $this->assertSame($before, DB::table('mm_journal_entries')->orderBy('id')->get()->map(fn ($row) => [$row->voucher_number, $row->total_credit, $row->deleted_at])->all());
            $migration->down();
            $this->assertSame(['SALES', 'PURCHASE', 'SALES', 'PURCHASE', 'PAYMENT', 'RECEIPT', 'OPENING'], DB::table('mm_journal_entries')->orderBy('id')->pluck('voucher_type')->all());
        } finally {
            Schema::dropIfExists('mm_journal_entries');
        }
    }

    public function test_invoice_and_bill_accounting_context_and_tax_categories_remain_correct(): void
    {
        foreach (['Invoice' => ['INVOICE', 'Invoice', 'invoice', true], 'Bill' => ['BILL', 'Purchase', 'bill', false]] as $type => [$voucher, $module, $refModule, $isSales]) {
            $invoice = new Invoice(['invoice_type' => $type, 'plant_id' => 1, 'partner_id' => 1, 'invoice_number' => '00001']);
            $invoice->setRelation('partner', new \App\Models\Patron(['legal_name' => 'Party']));
            $invoice->setRelation('plant', new \App\Models\Plant(['entity_id' => 1]));
            $context = (new \ReflectionMethod($invoice, 'buildPostingContext'))->invoke($invoice);
            $this->assertSame($voucher, $context['voucherType']);
            $this->assertSame($module, $context['module']);
            $this->assertSame($refModule, $context['refModule']);
            $this->assertSame($isSales, $context['isSales']);
            $this->assertSame($refModule, $invoice->getDocumentType());
        }
    }

    public function test_bulk_export_filters_and_journal_lookup_include_canonical_and_historical_types(): void
    {
        DB::statement('CREATE TABLE mm_invoices (id INTEGER PRIMARY KEY, plant_id INTEGER, invoice_type TEXT, invoice_label TEXT, invoice_date TEXT, invoice_number TEXT, prefix TEXT, account_id INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE mm_journal_entries (id INTEGER PRIMARY KEY, plant_id INTEGER, ref_id INTEGER, ref_module TEXT, voucher_number TEXT, is_deleted INTEGER, deleted_at TEXT)');
        try {
            foreach ([1 => ['Invoice', 'Dispatch'], 2 => ['Bill', 'purchase'], 3 => ['sales', 'Manual'], 4 => ['purchase', 'Manual'], 5 => ['credit_note', 'Credit Note']] as $id => [$type, $label]) {
                DB::table('mm_invoices')->insert(['id' => $id, 'plant_id' => 1, 'invoice_type' => $type, 'invoice_label' => $label, 'invoice_date' => '2026-10-03']);
            }
            DB::table('mm_journal_entries')->insert(['id' => 1, 'plant_id' => 1, 'ref_id' => 1, 'ref_module' => 'invoice', 'voucher_number' => 'CANONICAL-INVOICE-JOURNAL', 'is_deleted' => 0]);
            $query = new \App\Services\Reports\BulkDocumentQuery;
            $filters = ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
            $this->assertSame([1, 2, 3, 4], $query->build(1, $filters)->pluck('id')->all());
            $this->assertSame([1, 3], $query->build(1, $filters + ['type' => 'invoice'])->pluck('id')->all());
            $this->assertSame([2, 4], $query->build(1, $filters + ['type' => 'bill'])->pluck('id')->all());
            $this->assertSame([1], $query->build(1, $filters + ['subtype' => 'dispatch_invoice'])->pluck('id')->all());
            $this->assertSame([2], $query->build(1, $filters + ['subtype' => 'vendor_bill'])->pluck('id')->all());
            DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values));
            $this->assertSame([1], $query->build(1, $filters + ['reference' => 'CANONICAL-INVOICE-JOURNAL'])->pluck('id')->all());
        } finally {
            Schema::dropIfExists('mm_journal_entries');
            Schema::dropIfExists('mm_invoices');
        }
    }

    public function test_purchase_bill_relationships_include_bill_and_legacy_types_with_label_case_variants(): void
    {
        DB::statement('CREATE TABLE mm_invoices (id INTEGER PRIMARY KEY, ref_id INTEGER, plant_id INTEGER, invoice_type TEXT, invoice_label TEXT, deleted_at TEXT)');
        try {
            foreach ([1 => ['Bill', 'purchase'], 2 => ['bill', 'Purchase'], 3 => ['purchase', 'PURCHASE'], 4 => ['Invoice', 'purchase']] as $id => [$type, $label]) {
                DB::table('mm_invoices')->insert(['id' => $id, 'ref_id' => 7, 'plant_id' => 1, 'invoice_type' => $type, 'invoice_label' => $label]);
            }
            $order = new \App\Models\PurchaseOrder;
            $order->id = 7;
            $this->assertSame([1, 2, 3], $order->bills()->orderBy('id')->pluck('id')->all());
            $this->assertSame([1, 2, 3], $order->billingHistory()->orderBy('id')->pluck('id')->all());
        } finally {
            Schema::dropIfExists('mm_invoices');
        }
    }

    public function test_type_value_migration_normalizes_deleted_records_and_preserves_notes_and_amounts(): void
    {
        DB::statement('CREATE TABLE mm_invoices (id INTEGER PRIMARY KEY, invoice_type TEXT, total_amount INTEGER, deleted_at TEXT)');
        try {
            foreach (['sales', 'INVOICE', 'purchase', 'bill', 'credit_note', 'debit_note'] as $index => $type) {
                DB::table('mm_invoices')->insert(['id' => $index + 1, 'invoice_type' => $type, 'total_amount' => 100, 'deleted_at' => $index === 0 ? '2026-10-01' : null]);
            }
            $migration = require database_path('migrations/2026_10_03_130000_normalize_invoice_type_values.php');
            $migration->up();
            $this->assertSame(['Invoice', 'Invoice', 'Bill', 'Bill', 'credit_note', 'debit_note'], DB::table('mm_invoices')->orderBy('id')->pluck('invoice_type')->all());
            $this->assertSame(600, (int) DB::table('mm_invoices')->sum('total_amount'));
            $this->assertSame('2026-10-01', DB::table('mm_invoices')->find(1)->deleted_at);
            $migration->down();
            $this->assertSame(['sales', 'sales', 'bill', 'bill', 'credit_note', 'debit_note'], DB::table('mm_invoices')->orderBy('id')->pluck('invoice_type')->all());
        } finally {
            Schema::dropIfExists('mm_invoices');
        }
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    public function test_retired_fields_are_ignored_and_saved_as_null(): void
    {
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('plant_id');
            $table->string('invoice_type')->nullable(); $table->string('invoice_label')->nullable();
            $table->string('document_type')->nullable(); $table->string('document_source')->nullable();
            $table->string('prefix')->nullable(); $table->string('invoice_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('adjustment')->default(0); $table->decimal('shipping_charges')->default(0);
            $table->decimal('round_off')->default(0); $table->timestamps(); $table->softDeletes();
        });
        try {
            foreach (['sales' => 'Invoice', 'bill' => 'Bill'] as $legacy => $type) {
                $invoice = Invoice::create(['plant_id' => 1, 'invoice_type' => $legacy, 'invoice_label' => 'Manual',
                    'document_type' => 'OTHER', 'document_source' => 'OTHER']);
                $this->assertSame($type, $invoice->invoice_type);
                $this->assertSame('Manual', $invoice->invoice_label);
                $this->assertNull($invoice->document_type);
                $this->assertNull($invoice->document_source);
                $invoice->document_type = 'BILL';
                $invoice->document_source = 'DISPATCH';
                $invoice->save();
                $row = DB::table('mm_invoices')->find($invoice->id);
                $this->assertNull($row->document_type);
                $this->assertNull($row->document_source);
            }
        } finally {
            Schema::dropIfExists('mm_invoices');
        }
    }

    public function test_create_and_update_validation_does_not_use_retired_fields(): void
    {
        foreach ([StoreInvoiceRequest::class, UpdateInvoiceRequest::class] as $class) {
            $request = $class::create('/', 'POST', ['invoice_type' => 'Invoice']);
            $rules = $request->rules();
            $this->assertArrayNotHasKey('document_type', $rules);
            $this->assertArrayNotHasKey('document_source', $rules);
            foreach (['Invoice', 'Bill'] as $type) {
                $validator = Validator::make(['invoice_type' => $type, 'invoice_label' => 'Manual',
                    'document_type' => 'Invalid', 'document_source' => 'Invalid'],
                    array_intersect_key($rules, array_flip(['invoice_type', 'invoice_label'])));
                $this->assertTrue($validator->passes());
                $this->assertArrayNotHasKey('document_type', $validator->validated());
                $this->assertArrayNotHasKey('document_source', $validator->validated());
            }
        }
    }

    public function test_retirement_migration_clears_active_and_deleted_rows_and_keeps_legacy_fields(): void
    {
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id(); $table->string('invoice_type'); $table->string('invoice_label');
            $table->enum('document_type', ['INVOICE', 'BILL'])->nullable();
            $table->enum('document_source', ['DISPATCH', 'PURCHASE_STOCKIN', 'MANUAL'])->nullable();
            $table->softDeletes();
        });
        try {
            DB::table('mm_invoices')->insert([
                ['invoice_type' => 'Invoice', 'invoice_label' => 'Dispatch', 'document_type' => 'INVOICE', 'document_source' => 'DISPATCH', 'deleted_at' => null],
                ['invoice_type' => 'Bill', 'invoice_label' => 'Manual', 'document_type' => 'BILL', 'document_source' => 'MANUAL', 'deleted_at' => '2026-10-07 00:00:00'],
            ]);
            $migration = require database_path('migrations/2026_10_07_120000_retire_invoice_document_classification.php');
            $migration->up();
            foreach (DB::table('mm_invoices')->get() as $row) {
                $this->assertNull($row->document_type);
                $this->assertNull($row->document_source);
            }
            $this->assertSame(['Invoice', 'Bill'], DB::table('mm_invoices')->pluck('invoice_type')->all());
            $this->assertSame(['Dispatch', 'Manual'], DB::table('mm_invoices')->pluck('invoice_label')->all());
            DB::table('mm_invoices')->insert(['invoice_type' => 'Bill', 'invoice_label' => 'Manual']);
            $this->assertNull(DB::table('mm_invoices')->latest('id')->first()->document_type);
        } finally {
            Schema::dropIfExists('mm_invoices');
        }
    }
}
