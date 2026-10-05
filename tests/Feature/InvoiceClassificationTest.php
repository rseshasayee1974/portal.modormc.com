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
    public function test_invoice_and_bill_accounting_context_and_tax_categories_remain_correct(): void
    {
        foreach (['Invoice' => ['SALES', 'Invoice', 'invoice', true], 'Bill' => ['PURCHASE', 'Purchase', 'bill', false]] as $type => [$voucher, $module, $refModule, $isSales]) {
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

    public function test_model_save_events_persist_classification_and_use_bill_number_series(): void
    {
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->string('invoice_type')->nullable();
            $table->string('invoice_label')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_source')->nullable();
            $table->string('prefix')->nullable();
            $table->string('invoice_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('adjustment')->default(0);
            $table->decimal('shipping_charges')->default(0);
            $table->decimal('round_off')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        try {
            foreach ([['INVOICE', 'DISPATCH'], ['INVOICE', 'MANUAL'], ['BILL', 'PURCHASE_STOCKIN'], ['BILL', 'MANUAL']] as [$type, $source]) {
                $invoice = Invoice::create(['plant_id' => 1, 'document_type' => $type, 'document_source' => $source]);
                $row = DB::table('mm_invoices')->find($invoice->id);
                $this->assertSame($type, $row->document_type);
                $this->assertSame($source, $row->document_source);
                $this->assertSame($type === 'BILL' ? 'Bill' : 'Invoice', $row->invoice_type);
                if ($type === 'BILL') $this->assertStringStartsWith('Bill/', $row->prefix);
            }
        } finally {
            Schema::dropIfExists('mm_invoices');
        }
    }

    public function test_legacy_creation_and_canonical_payloads_keep_accounting_fields_compatible(): void
    {
        foreach ([
            ['sales', 'Dispatch', 'INVOICE', 'DISPATCH'],
            ['sales', 'Tax Invoice', 'INVOICE', 'MANUAL'],
            ['bill', 'purchase', 'BILL', 'PURCHASE_STOCKIN'],
            ['bill', 'Manual', 'BILL', 'MANUAL'],
            ['purchase', 'stock-in', 'BILL', 'PURCHASE_STOCKIN'],
        ] as [$legacyType, $label, $type, $source]) {
            $invoice = new Invoice(['invoice_type' => $legacyType, 'invoice_label' => $label]);
            $invoice->normalizeDocumentClassification();
            $this->assertSame($type, $invoice->document_type);
            $this->assertSame($source, $invoice->document_source);
            $this->assertSame($type === 'BILL' ? 'Bill' : 'Invoice', $invoice->invoice_type);
        }

        foreach ([['INVOICE', 'DISPATCH', 'Invoice', 'Dispatch'], ['BILL', 'PURCHASE_STOCKIN', 'Bill', 'purchase'], ['INVOICE', 'MANUAL', 'Invoice', 'Manual'], ['BILL', 'MANUAL', 'Bill', 'Manual']] as [$type, $source, $legacyType, $label]) {
            $invoice = new Invoice(['document_type' => $type, 'document_source' => $source]);
            $invoice->normalizeDocumentClassification();
            $this->assertSame($legacyType, $invoice->invoice_type);
            $this->assertSame($label, $invoice->invoice_label);
        }
    }

    public function test_edit_preserves_backfilled_source_and_legacy_edits_refresh_classification(): void
    {
        $invoice = new Invoice;
        $invoice->setRawAttributes(['invoice_type' => 'sales', 'invoice_label' => 'Tax Invoice', 'document_type' => 'INVOICE', 'document_source' => 'DISPATCH'], true);
        $invoice->notes = 'Updated';
        $invoice->normalizeDocumentClassification();
        $this->assertSame('DISPATCH', $invoice->document_source);
        $invoice->syncOriginal();
        $invoice->invoice_label = 'Manual';
        $invoice->normalizeDocumentClassification();
        $this->assertSame('MANUAL', $invoice->document_source);
    }

    public function test_credit_notes_remain_distinct(): void
    {
        $invoice = new Invoice(['invoice_type' => 'credit_note', 'invoice_label' => 'Credit Note']);
        $invoice->normalizeDocumentClassification();
        $this->assertNull($invoice->document_type);
        $this->assertNull($invoice->document_source);
        $this->assertSame('credit_note', $invoice->invoice_type);
    }

    public function test_model_rejects_purchase_dispatch_combination(): void
    {
        $this->expectException(ValidationException::class);
        (new Invoice(['document_type' => 'BILL', 'document_source' => 'DISPATCH']))->normalizeDocumentClassification();
    }

    public function test_store_and_update_requests_validate_classification_values_and_pairs(): void
    {
        foreach ([StoreInvoiceRequest::class, UpdateInvoiceRequest::class] as $class) {
            foreach ([
                ['sales', 'INVOICE', 'MANUAL', true],
                ['bill', 'BILL', 'PURCHASE_STOCKIN', true],
                ['sales', 'INVOICE', 'DISPATCH', true],
                ['bill', 'BILL', 'MANUAL', true],
                ['bill', 'BILL', 'DISPATCH', false],
                ['sales', 'BILL', 'MANUAL', false],
                ['sales', 'INVOICE', 'OTHER', false],
                ['sales', 'OTHER', 'MANUAL', false],
            ] as [$legacyType, $type, $source, $passes]) {
                $request = $class::create('/', 'POST', ['invoice_type' => $legacyType, 'document_type' => $type, 'document_source' => $source]);
                (new \ReflectionMethod($request, 'prepareDocumentClassification'))->invoke($request);
                $rules = array_intersect_key($request->rules(), array_flip(['invoice_type', 'invoice_label', 'document_type', 'document_source']));
                $validator = Validator::make($request->all(), $rules);
                $request->withValidator($validator);
                $this->assertSame($passes, $validator->passes(), $class.' '.$type.' '.$source);
            }
        }
    }

    public function test_migration_backfills_linked_and_deleted_records_and_rolls_back_without_changing_legacy_data(): void
    {
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->default(1);
            $table->string('invoice_type')->nullable();
            $table->string('invoice_label')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('mm_dispatch_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('invoice_id'));
        Schema::create('mm_purchase_orders', fn (Blueprint $table) => $table->unsignedBigInteger('billing_id'));
        DB::table('mm_invoices')->insert([
            ['id' => 1, 'invoice_type' => 'sales', 'invoice_label' => 'Tax Invoice', 'deleted_at' => null],
            ['id' => 2, 'invoice_type' => 'bill', 'invoice_label' => 'Manual', 'deleted_at' => '2026-10-01 00:00:00'],
            ['id' => 3, 'invoice_type' => 'sales', 'invoice_label' => 'Manual', 'deleted_at' => null],
            ['id' => 4, 'invoice_type' => 'bill', 'invoice_label' => 'purchase', 'deleted_at' => null],
            ['id' => 5, 'invoice_type' => 'credit_note', 'invoice_label' => 'Credit Note', 'deleted_at' => null],
            ['id' => 6, 'invoice_type' => 'bill', 'invoice_label' => 'Manual', 'deleted_at' => null],
        ]);
        DB::table('mm_dispatch_statuses')->insert(['invoice_id' => 1]);
        DB::table('mm_purchase_orders')->insert(['billing_id' => 2]);
        $before = DB::table('mm_invoices')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_03_120000_add_document_classification_to_invoices.php');
        try {
            $migration->up();
            foreach ([1 => ['INVOICE', 'DISPATCH'], 2 => ['BILL', 'PURCHASE_STOCKIN'], 3 => ['INVOICE', 'MANUAL'], 4 => ['BILL', 'PURCHASE_STOCKIN'], 5 => [null, null], 6 => ['BILL', 'MANUAL']] as $id => [$type, $source]) {
                $row = DB::table('mm_invoices')->find($id);
                $this->assertSame($type, $row->document_type);
                $this->assertSame($source, $row->document_source);
            }
            $migration->down();
            $this->assertSame($before, DB::table('mm_invoices')->orderBy('id')->get()->toJson());
        } finally {
            Schema::dropIfExists('mm_purchase_orders');
            Schema::dropIfExists('mm_dispatch_statuses');
            Schema::dropIfExists('mm_invoices');
        }
    }
}
