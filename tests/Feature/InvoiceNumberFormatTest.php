<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 15:48:43 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoiceNumberFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'number_format_test', 'database.connections.number_format_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));
        session(['active_plant_id' => 1]);
        Schema::create('mm_custom_settings', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('module_name');
            $table->integer('module_id')->nullable(); $table->json('settings'); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('mm_invoices', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id');
            $table->string('invoice_type')->nullable(); $table->string('invoice_label')->nullable();
            $table->string('document_type')->nullable(); $table->string('document_source')->nullable();
            $table->string('prefix')->nullable(); $table->string('invoice_number')->nullable();
            $table->integer('account_id')->nullable(); $table->string('status')->default('Draft');
            $table->boolean('is_active')->default(true);
            $table->decimal('adjustment')->default(0); $table->decimal('shipping_charges')->default(0);
            $table->decimal('round_off')->default(0); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('mm_ledgers', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('title')->default('Ledger'); $table->string('prefix')->nullable(); $table->string('alias')->nullable(); $table->string('description')->nullable(); $table->softDeletes();
        });
        DB::table('mm_ledgers')->insert([['id' => 10, 'plant_id' => 1, 'prefix' => 'IGNORED', 'description' => 'JG'], ['id' => 11, 'plant_id' => 1, 'prefix' => 'IGNORED', 'description' => 'PB']]);
        $this->settings();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function settings(array $overrides = []): void
    {
        $settings = array_merge([
            'invoice_number_format' => 'prefix_number_fy',
            'invoice_number_start' => 12, 'invoice_number_padding' => 0,
            'billing_number_format' => 'prefix_number_fy',
            'billing_number_start' => 12, 'billing_number_padding' => 0,
        ], $overrides);
        DB::table('mm_custom_settings')->updateOrInsert(['plant_id' => 1, 'module_name' => 'batching'], ['settings' => json_encode($settings)]);
    }

    public function test_invoice_and_billing_use_the_requested_format_and_share_matching_series(): void
    {
        foreach (['Invoice' => 12, 'Bill' => 13] as $type => $sequence) {
            $details = Invoice::generateNumber(1, $type, 10);
            $this->assertSame('JG/' . $sequence . '/26-27', $details['full_number']);
            $invoice = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => $type, 'account_id' => 10]);
            $this->assertSame($details['full_number'], $invoice->full_number);
            $this->assertSame('JG/', $invoice->prefix);
            $this->assertSame($sequence . '/26-27', $invoice->invoice_number);
        }
    }

    public function test_next_invoice_reuses_a_soft_deleted_number_inside_the_series(): void
    {
        $attributes = ['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice'];
        $first = Invoice::create($attributes);
        $second = Invoice::create($attributes);
        DB::table('mm_invoices')->where('id', $first->id)->update(['deleted_at' => now()]);

        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('JG/12/26-27', Invoice::create($attributes)->full_number);
        $this->assertSame('JG/13/26-27', $second->full_number);
        $this->assertSame('JG/14/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
    }

    public function test_billing_prefix_and_padding_are_independent_of_invoice_settings(): void
    {
        $this->settings(['billing_number_start' => 7, 'billing_number_padding' => 3]);
        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('PB/007/26-27', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        $this->assertSame('PB/007/26-27', Invoice::create(['plant_id' => 1, 'account_id' => 11, 'invoice_type' => 'Bill'])->full_number);
    }

    public function test_series_are_scoped_to_plant_and_financial_year(): void
    {
        Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertSame('JG/13/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('INV/26-27/1', Invoice::generateNumber(2)['full_number']);
        Carbon::setTestNow('2027-03-31 12:00:00');
        $this->assertSame('JG/13/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        Carbon::setTestNow('2027-04-01 12:00:00');
        $this->assertSame('JG/12/27-28', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $nextYear = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertSame('JG/12/27-28', $nextYear->full_number);
    }

    public function test_manual_sequence_and_full_number_are_normalized_and_duplicates_rejected(): void
    {
        $details = Invoice::generateNumber(1, 'Invoice', 10);
        foreach (['12', '00012', '12/26-27', 'JG/12/26-27'] as $number) {
            $this->assertSame('12/26-27', Invoice::normalizeNumber($number, $details));
        }
        Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertTrue(Invoice::numberExists(1, 'JG/', '00012/26-27'));
        $this->assertFalse(Invoice::numberExists(1, 'JG/', '12/25-26'));
        $this->assertFalse(Invoice::numberExists(2, 'JG/', '12/26-27'));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already in use');
        Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Bill', 'invoice_number' => 'JG/12/26-27']);
    }

    public function test_create_request_accepts_slashes_and_checks_the_same_series(): void
    {
        $request = StoreInvoiceRequest::create('/', 'POST', ['account_id' => 10, 'invoice_type' => 'Invoice', 'invoice_number' => 'JG/12/26-27']);
        (new \ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
        $this->assertSame('JG/', $request->input('prefix'));
        $this->assertSame('12/26-27', $request->input('invoice_number'));
        $rules = array_intersect_key($request->rules(), ['invoice_number' => true]);
        $this->assertTrue(Validator::make($request->all(), $rules)->passes());
        Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertFalse(Validator::make($request->all(), $rules)->passes());
    }

    public function test_legacy_format_and_existing_document_numbers_stay_unchanged(): void
    {
        DB::table('mm_invoices')->insert(['plant_id' => 1, 'prefix' => 'INN/26-27/', 'invoice_number' => '00102']);
        $legacy = Invoice::withoutGlobalScopes()->first();
        $this->assertSame('INN/26-27/00102', $legacy->full_number);
        $custom = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->settings(['invoice_number_format' => 'ledger', 'billing_number_format' => 'ledger']);
        $this->assertSame('JG/26-27/12', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('JG/26-27/12', Invoice::generateNumber(1, 'Bill', 10)['full_number']);
        $this->assertSame('JG/12/26-27', $custom->fresh()->full_number);
        $this->assertSame('INN/26-27/00102', $legacy->fresh()->full_number);
    }

    public function test_full_manual_number_with_the_wrong_financial_year_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Invoice::normalizeNumber('JG/12/25-26', Invoice::generateNumber(1, 'Invoice', 10));
    }

    public function test_editing_a_custom_sequence_keeps_its_original_financial_year(): void
    {
        $invoice = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        Carbon::setTestNow('2027-04-01 12:00:00');
        $this->settings(['invoice_number_format' => 'ledger']);
        $invoice->invoice_number = '14';
        $invoice->save();
        $this->assertSame('JG/14/26-27', $invoice->fresh()->full_number);
    }

    public function test_custom_settings_save_applies_invoice_format_and_preserves_billing_format(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->once()->with('edit');
        DB::table('mm_ledgers')->where('id', 10)->update(['description' => 'AC']);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => [
            'invoice_number_format' => 'prefix_number_fy',
            'invoice_number_start' => 7, 'invoice_number_padding' => 3,
        ]]);
        $controller->update($request);
        $this->assertSame('AC/007/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('AC/12/26-27', Invoice::generateNumber(1, 'Bill', 10)['full_number']);
    }

    public function test_invoice_module_numeric_setting_switches_format_and_preserves_sequence_start(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->twice()->with('edit');
        DB::table('mm_custom_settings')->insert(['plant_id' => 1, 'module_name' => 'invoice', 'settings' => json_encode(['other_option' => 1])]);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'invoice', 'settings' => [
            'invoice_number_format' => '1', 'invoice_number_start' => 12, 'invoice_number_padding' => 0,
        ]]));
        $settings = \App\Models\CustomSetting::getForModule(1, 'invoice');
        $this->assertSame(1, $settings['invoice_number_format']);
        $this->assertSame(1, $settings['other_option']);
        $this->assertSame('JG/12/26-27', Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice'])->full_number);
        DB::table('mm_ledgers')->where('id', 10)->update(['description' => '']);
        $this->assertSame('INV/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        DB::table('mm_ledgers')->where('id', 10)->update(['description' => 'JG']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'invoice', 'settings' => ['invoice_number_format' => 0]]));
        $this->assertSame('JG/26-27/12', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('PB/12/26-27', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        $this->assertSame('JG/12/26-27', Invoice::withoutGlobalScopes()->first()->full_number);
        $this->assertSame(12, \App\Models\CustomSetting::getForModule(1, 'invoice')['invoice_number_start']);
    }

    public function test_number_format_save_from_settings_page_writes_numeric_module_setting(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->twice()->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => [
            'invoice_number_format' => 1, 'invoice_number_start' => 12, 'invoice_number_padding' => 0,
            'billing_number_format' => 0,
        ]]));
        $this->assertSame(1, \App\Models\CustomSetting::getForModule(1, 'invoice')['invoice_number_format']);
        $this->assertSame(0, \App\Models\CustomSetting::getForModule(1, 'billing')['billing_number_format']);
        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('PB/26-27/12', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => ['camera' => 1]]));
        $this->assertSame(1, \App\Models\CustomSetting::getForModule(1, 'invoice')['invoice_number_format']);
    }

    public function test_selected_ledger_description_overrides_old_custom_prefix_values_and_client_prefixes(): void
    {
        DB::table('mm_ledgers')->where('id', 10)->update(['alias' => 'WRONG']);
        $this->settings(['invoice_number_prefix' => 'IGNORED', 'billing_number_prefix' => 'IGNORED']);
        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('PB/12/26-27', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        $invoice = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice', 'prefix' => 'FAKE/']);
        $this->assertSame('JG/12/26-27', $invoice->full_number);
    }

    public function test_blank_ledger_prefix_uses_invoice_and_billing_defaults(): void
    {
        DB::table('mm_ledgers')->where('id', 10)->update(['description' => '']);
        $this->assertSame('INV/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('BILL/12/26-27', Invoice::generateNumber(1, 'Bill', 10)['full_number']);
        $this->settings(['invoice_number_format' => 'ledger', 'billing_number_format' => 'ledger']);
        $this->assertSame('INV/26-27/12', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('BILL/26-27/12', Invoice::generateNumber(1, 'Bill', 10)['full_number']);
    }

    public function test_invoice_and_billing_creation_normalize_description_prefixes_and_empty_defaults(): void
    {
        Schema::table('mm_ledgers', fn (Blueprint $table) => $table->dropColumn('prefix'));
        foreach (['JG', 'JG/{FY}/', '', null, '   '] as $description) {
            DB::table('mm_ledgers')->where('id', 10)->update(['description' => $description]);
            foreach (['Invoice' => 'INV/', 'Bill' => 'BILL/'] as $type => $defaultPrefix) {
                // Each case starts a fresh series in this isolated in-memory database.
                DB::table('mm_invoices')->delete();
                $prefix = in_array($description, ['JG', 'JG/{FY}/'], true) ? 'JG/' : $defaultPrefix;
                $expected = $prefix . '12/26-27';
                $this->assertSame($expected, Invoice::generateNumber(1, $type, 10)['full_number']);
                $request = new class extends StoreInvoiceRequest {
                    public function prepareNumbering(): void { $this->prepareForValidation(); }
                };
                $request->merge(['invoice_type' => $type, 'account_id' => 10, 'invoice_number' => $expected, 'prefix' => 'STALE/']);
                $request->prepareNumbering();
                $this->assertSame($prefix, $request->input('prefix'));
                $this->assertSame('12/26-27', $request->input('invoice_number'));
                $invoice = Invoice::create(array_merge($request->only(['invoice_type', 'account_id', 'prefix', 'invoice_number']), [
                    'plant_id' => 1, 'invoice_label' => 'Manual',
                ]));
                $this->assertSame($expected, $invoice->fresh()->full_number);
                $this->assertSame($prefix . '13/26-27', Invoice::generateNumber(1, $type, 10)['full_number']);
            }
        }
    }

    public function test_default_layout_creates_invoice_billing_purchase_and_dispatch_numbers_without_padding(): void
    {
        $this->settings(['invoice_number_format' => 0, 'billing_number_format' => 0]);
        foreach (['Invoice', 'Bill', 'Purchase', 'Dispatch'] as $type) {
            DB::table('mm_invoices')->delete();
            DB::table('mm_invoices')->insert(['plant_id' => 1, 'prefix' => 'JG/26-27/', 'invoice_number' => '00011']);
            $details = Invoice::generateNumber(1, $type, 10);
            $this->assertSame('JG/26-27/12', $details['full_number']);
            $this->assertSame('12', Invoice::normalizeNumber('JG/26-27/00012', $details));
            $invoice = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => $type, 'invoice_label' => 'Manual']);
            $this->assertSame('JG/26-27/12', $invoice->fresh()->full_number);
            $this->assertSame('12', $invoice->invoice_number);
            $this->assertTrue(Invoice::numberExists(1, 'JG/26-27/', '00012'));
            $this->assertSame('00011', DB::table('mm_invoices')->orderBy('id')->value('invoice_number'));
        }
    }

    public function test_default_invoice_and_billing_layouts_apply_independent_start_and_padding(): void
    {
        DB::table('mm_custom_settings')->delete();
        $this->assertSame('JG/26-27/1', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('PB/26-27/1', Invoice::generateNumber(1, 'Bill', 11)['full_number']);

        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->once()->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['module' => 'batching', 'settings' => [
            'invoice_number_format' => 0, 'invoice_number_start' => 7, 'invoice_number_padding' => 3,
            'billing_number_format' => 0, 'billing_number_start' => 25, 'billing_number_padding' => 4,
        ]]));

        $invoice = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertSame('JG/26-27/007', $invoice->fresh()->full_number);
        $this->assertSame('JG/26-27/008', Invoice::generateNumber(1, 'Dispatch', 10)['full_number']);
        $bill = Invoice::create(['plant_id' => 1, 'account_id' => 11, 'invoice_type' => 'Bill']);
        $this->assertSame('PB/26-27/0025', $bill->fresh()->full_number);
        $this->assertSame('PB/26-27/0026', Invoice::generateNumber(1, 'Purchase', 11)['full_number']);
    }

    public function test_quotation_formats_padding_and_partial_settings_preserve_sequence(): void
    {
        Schema::create('mm_quotations', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('reference'); $table->softDeletes();
        });
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(8)->with('edit');
        foreach ([
            ['prefix_number_fy', 0, 'QT/1/26-27'],
            ['prefix_fy_number', 0, 'QT/26-27/2'],
            ['prefix_compact_fy_number', 0, 'QT/2627/3'],
            ['prefix_compact_fy_number', 4, 'QT/2627/0004'],
        ] as [$format, $padding, $expected]) {
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => [
                'quote_number_format' => $format, 'quote_number_padding' => (string) $padding,
            ]]));
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
            $this->assertSame($expected, \App\Models\Quotation::generateReference(1));
            DB::table('mm_quotations')->insert(['plant_id' => 1, 'reference' => $expected]);
        }
        DB::table('mm_quotations')->where('reference', 'QT/2627/0004')->update(['deleted_at' => now()]);
        DB::table('mm_quotations')->insert(['plant_id' => 1, 'reference' => 'QT/2627/999-invalid']);
        $this->assertSame('QT/2627/0005', \App\Models\Quotation::generateReference(1));
        $this->assertSame('QT/26-27/1', \App\Models\Quotation::generateReference(2));
        Carbon::setTestNow(Carbon::parse('2027-04-01 12:00:00'));
        $this->assertSame('QT/2728/0001', \App\Models\Quotation::generateReference(1));
        $this->assertSame('QT/1/26-27', DB::table('mm_quotations')->orderBy('id')->value('reference'));
    }

    public function test_compact_invoice_format_saves_and_generates_padded_numbers(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(3)->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => [
            'invoice_number_format' => 'prefix_compact_fy_number', 'invoice_number_start' => 1, 'invoice_number_padding' => 4,
        ]]));
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
        $details = Invoice::generateNumber(1, 'Invoice', 10);
        $this->assertSame('JG/2627/0001', $details['full_number']);
        $this->assertSame('0001', Invoice::normalizeNumber('JG/2627/0001', $details));
        $first = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => 'Invoice']);
        $this->assertSame('JG/2627/0001', $first->fresh()->full_number);
        $this->assertTrue(Invoice::numberExists(1, 'JG/2627/', '1'));
        $this->assertSame('JG/2627/0002', Invoice::generateNumber(1, 'Dispatch', 10)['full_number']);
        $this->assertSame('INV/2627/0001', Invoice::generateNumber(1)['full_number']);
        $this->assertSame('INV/26-27/1', Invoice::generateNumber(2)['full_number']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['invoice_number_padding' => 0]]));
        $this->assertSame('JG/2627/2', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        Carbon::setTestNow(Carbon::parse('2027-04-01 12:00:00'));
        $this->assertSame('JG/2728/1', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('JG/2627/0001', $first->fresh()->full_number);
    }

    public function test_billing_compact_format_and_padding_are_saved_independently(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(5)->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => [
            'billing_number_format' => 'prefix_compact_fy_number', 'billing_number_start' => 1, 'billing_number_padding' => 4,
        ]]));
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
        $this->assertSame('BILL/2627/0001', Invoice::generateNumber(1, 'Bill')['full_number']);
        $details = Invoice::generateNumber(1, 'Purchase', 11);
        $this->assertSame('PB/2627/0001', $details['full_number']);
        $this->assertSame('0001', Invoice::normalizeNumber('PB/2627/0001', $details));
        $first = Invoice::create(['plant_id' => 1, 'account_id' => 11, 'invoice_type' => 'Bill']);
        $this->assertSame('PB/2627/0001', $first->fresh()->full_number);
        $this->assertTrue(Invoice::numberExists(1, 'PB/2627/', '1'));
        $this->assertSame('PB/2627/0002', Invoice::generateNumber(1, 'Purchase', 11)['full_number']);
        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
        $this->assertSame('BILL/26-27/1', Invoice::generateNumber(2, 'Bill')['full_number']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['billing_number_padding' => 0]]));
        $this->assertSame('PB/2627/2', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        Carbon::setTestNow(Carbon::parse('2027-04-01 12:00:00'));
        $this->assertSame('PB/2728/1', Invoice::generateNumber(1, 'Bill', 11)['full_number']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['billing_number_format' => 0]]));
        $this->assertSame('BILL/26-27/1', Invoice::generateNumber(1, 'Bill')['full_number']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['billing_number_format' => 1]]));
        $this->assertSame('BILL/1/26-27', Invoice::generateNumber(1, 'Bill')['full_number']);
        $this->assertSame('PB/2627/0001', $first->fresh()->full_number);
    }

    public function test_other_document_generators_use_prefix_financial_year_sequence(): void
    {
        foreach (['mm_quotations', 'mm_customer_pos', 'mm_sales_orders', 'mm_purchase_orders', 'mm_purchase_order_history', 'mm_petty_cash'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->integer('plant_id'); $table->string('prefix')->nullable();
                $table->integer('customer_id')->nullable();
                $table->string('reference')->nullable(); $table->string('order_no')->nullable();
                $table->string('po_number')->nullable(); $table->string('inward_no')->nullable();
                $table->string('ref_no')->nullable(); $table->timestamps(); $table->softDeletes();
            });
        }
        $this->assertSame('QT/26-27/1', \App\Models\Quotation::generateReference(1));
        DB::table('mm_quotations')->insert(['plant_id' => 1, 'reference' => 'QT/26-27/11']);
        $this->assertSame('QT/26-27/12', \App\Models\Quotation::generateReference(1));
        $this->assertSame('CPO/26-27/1', \App\Models\CustomerPO::generateReference(1)['reference']);
        DB::table('mm_customer_pos')->insert(['plant_id' => 1, 'prefix' => 'CPO/26-27/', 'reference' => 'CPO/26-27/11']);
        $this->assertSame('CPO/26-27/12', \App\Models\CustomerPO::generateReference(1)['reference']);
        $this->settings(['cpo_number_format' => 'prefix_compact_fy_number', 'cpo_number_padding' => 4]);
        $this->assertSame('CPO/2627/0012', \App\Models\CustomerPO::generateReference(1)['reference']);
        DB::table('mm_customer_pos')->insert(['plant_id' => 1, 'prefix' => 'CPO/2627/', 'reference' => 'CPO/2627/0012']);
        $this->settings(['cpo_number_format' => 'prefix_number_fy', 'cpo_number_padding' => 0]);
        $this->assertSame('CPO/13/26-27', \App\Models\CustomerPO::generateReference(1)['reference']);
        DB::table('mm_customer_pos')->insert(['plant_id' => 1, 'prefix' => 'CPO/', 'reference' => 'CPO/13/26-27']);
        $this->settings(['cpo_number_format' => 'prefix_fy_number', 'cpo_number_padding' => 4]);
        $this->assertSame('CPO/26-27/0014', \App\Models\CustomerPO::generateReference(1)['reference']);
        $this->assertSame('ALT/26-27/0001', \App\Models\CustomerPO::generateReference(1, 'ALT')['reference']);
        $this->assertSame('CPO/26-27/1', \App\Models\CustomerPO::generateReference(2)['reference']);
        $this->settings();
        $this->assertSame('SO/26-27/0001', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/26-27/', 'order_no' => '11']);
        $this->assertSame('SO/26-27/0012', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/2627/', 'order_no' => '20']);
        $this->assertSame('SO/2627/0021', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        $this->assertSame('SO/26-27/0001', \App\Models\SalesOrder::generateOrderNo(1, 11)['full_number']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 11, 'prefix' => 'SO/26-27/', 'order_no' => '1']);
        $this->assertSame('SO/26-27/0002', \App\Models\SalesOrder::generateOrderNo(1, 11)['full_number']);
        $this->assertSame('SO/26-27/0001', \App\Models\SalesOrder::generateOrderNo(1, 12)['full_number']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/26-27/', 'order_no' => '22']);
        $this->assertSame('SO/2627/0023', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        session(['active_plant_id' => 2]);
        $this->assertSame('SO/26-27/0001', \App\Models\SalesOrder::generateOrderNo(2, 10)['full_number']);
        session(['active_plant_id' => 1]);
        Carbon::setTestNow(Carbon::parse('2027-04-01 12:00:00'));
        $this->assertSame('SO/2728/0001', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        $this->assertSame('SO/27-28/0001', \App\Models\SalesOrder::generateOrderNo(1, 11)['full_number']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));
        $this->assertSame('PO/26-27/1', \App\Models\PurchaseOrder::generateNextRefId(1, '2026-11-02')['ref_no']);
        DB::table('mm_purchase_orders')->insert(['plant_id' => 1, 'po_number' => 'PO/26-27/11', 'created_at' => now()]);
        $this->assertSame('PO/26-27/12', \App\Models\PurchaseOrder::generateNextRefId(1, '2026-11-02')['ref_no']);
        $this->assertSame('INW/26-27/1', \App\Models\PurchaseOrderHistory::generateNextInwardNo(1, '2026-11-02'));
        $this->assertSame('PC/26-27/1', \App\Models\PettyCash::generateRefNo(1, null, '2026-11-02'));
        $this->assertSame('JG/26-27/1', \App\Models\PettyCash::generateRefNo(1, 10, '2026-11-02'));
        DB::table('mm_petty_cash')->insert(['plant_id' => 1, 'ref_no' => 'JG/26-27/11']);
        $this->assertSame('JG/26-27/12', \App\Models\PettyCash::generateRefNo(1, 10, '2026-11-02'));
        $this->assertSame('BS/26-27/', \App\Models\Ledger::documentSeriesPrefix(1, null, '2026-11-02', 'BS'));
        $this->assertSame('PS/26-27/', \App\Models\Ledger::documentSeriesPrefix(1, null, '2026-11-02', 'PS'));
    }

    public function test_sales_order_format_and_padding_are_configurable_and_continue_existing_sequences(): void
    {
        Schema::create('mm_sales_orders', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('customer_id');
            $table->string('prefix'); $table->string('order_no'); $table->softDeletes();
        });
        DB::table('mm_sales_orders')->insert([
            'plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/2627/', 'order_no' => '00122',
        ]);
        $details = \App\Models\SalesOrder::generateOrderNo(1, 10);
        $this->assertSame('0123', $details['next_number']);
        $this->assertSame('SO/2627/0123', $details['full_number']);

        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(5)->with('edit');
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['so_number_padding' => '5']]));
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
        $this->assertSame('SO/2627/00123', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['so_number_padding' => 0]]));
        $this->assertSame('SO/2627/123', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        $this->assertSame('00122', DB::table('mm_sales_orders')->value('order_no'));

        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['so_number_format' => 'prefix_number_fy', 'so_number_padding' => 4]]));
        $this->assertSame('SO/0001/26-27', \App\Models\SalesOrder::generateOrderNo(1, 11)['full_number']);
        $this->assertSame('SO/0123/26-27', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/', 'order_no' => '0123/26-27']);
        DB::table('mm_sales_orders')->insert(['plant_id' => 1, 'customer_id' => 10, 'prefix' => 'SO/', 'order_no' => '0999/25-26']);
        $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['so_number_padding' => 0]]));
        $this->assertSame('SO/124/26-27', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
        $this->assertSame('SO/1/26-27', \App\Models\SalesOrder::generateOrderNo(1, 11)['full_number']);
        $this->settings(['so_number_format' => 'auto', 'so_number_padding' => 4]);
        $this->assertSame('SO/2627/0124', \App\Models\SalesOrder::generateOrderNo(1, 10)['full_number']);
    }

    public function test_preview_and_creation_use_default_prefix_without_a_ledger(): void
    {
        $details = Invoice::generateNumber(1);
        $this->assertFalse($details['requires_ledger']);
        $this->assertSame('INV/12/26-27', $details['full_number']);
        $this->assertSame('INV/12/26-27', Invoice::create(['plant_id' => 1, 'invoice_type' => 'Invoice'])->full_number);
        $this->assertSame('BILL/12/26-27', Invoice::create(['plant_id' => 1, 'invoice_type' => 'Bill'])->full_number);
    }

    public function test_a_ledger_from_another_plant_cannot_supply_the_prefix(): void
    {
        DB::table('mm_ledgers')->insert(['id' => 20, 'plant_id' => 2, 'prefix' => 'OTHER', 'description' => 'OTHER']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Invoice::generateNumber(1, 'Invoice', 20);
    }

    public function test_alias_migration_preserves_existing_prefixes_and_document_numbers(): void
    {
        DB::table('mm_invoices')->insert(['plant_id' => 1, 'prefix' => 'INN/26-27/', 'invoice_number' => '00102']);
        Schema::table('mm_ledgers', fn (Blueprint $table) => $table->dropColumn('alias'));
        $migration = require database_path('migrations/2026_11_02_155424_add_alias_to_ledgers_table.php');
        $migration->up();
        $this->assertSame('JG', DB::table('mm_ledgers')->where('id', 10)->value('alias'));
        DB::table('mm_ledgers')->where('id', 10)->update(['alias' => 'JG']);
        $migration->up();
        $this->assertSame('JG', DB::table('mm_ledgers')->where('id', 10)->value('alias'));
        $this->assertSame('INN/26-27/00102', Invoice::withoutGlobalScopes()->first()->full_number);
    }

    public function test_prefix_migration_carries_forward_legacy_codes_without_overwriting_prefixes(): void
    {
        DB::table('mm_ledgers')->insert([
            ['plant_id' => 1, 'alias' => 'AC', 'description' => 'OLD', 'prefix' => null],
            ['plant_id' => 1, 'alias' => null, 'description' => 'BANK/{type}/{fy}/', 'prefix' => null],
            ['plant_id' => 1, 'alias' => null, 'description' => 'A long ledger description', 'prefix' => null],
        ]);
        $migration = require database_path('migrations/2026_11_02_160906_add_document_prefix_to_ledgers_table.php');
        $migration->up();
        $migration->up();
        $this->assertSame('IGNORED', DB::table('mm_ledgers')->where('id', 10)->value('prefix'));
        $this->assertSame('AC', DB::table('mm_ledgers')->where('alias', 'AC')->value('prefix'));
        $this->assertSame('BANK/{type}/{fy}/', DB::table('mm_ledgers')->where('description', 'BANK/{type}/{fy}/')->value('prefix'));
        $this->assertNull(DB::table('mm_ledgers')->where('description', 'A long ledger description')->value('prefix'));

        Schema::table('mm_ledgers', fn (Blueprint $table) => $table->dropColumn('prefix'));
        $migration->up();
        $this->assertSame('AC', DB::table('mm_ledgers')->where('alias', 'AC')->value('prefix'));
    }

    public function test_prefix_lookup_rejects_other_plants_and_deleted_ledgers(): void
    {
        $this->assertSame('JG', \App\Models\Ledger::documentPrefix(1, 10));
        $this->assertSame('', \App\Models\Ledger::documentPrefix(2, 10));
        DB::table('mm_ledgers')->where('id', 10)->update(['deleted_at' => now()]);
        $this->assertSame('', \App\Models\Ledger::documentPrefix(1, 10));
    }

    public function test_description_migration_recovers_blank_values_and_removes_unused_prefix_column(): void
    {
        DB::table('mm_ledgers')->where('id', 11)->update(['description' => '', 'prefix' => 'RECOVERED']);
        $migration = require database_path('migrations/2026_11_02_161616_restore_ledger_description_numbering.php');
        $migration->up();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('mm_ledgers', 'prefix'));
        $this->assertSame('JG', \App\Models\Ledger::documentPrefix(1, 10));
        $this->assertSame('RECOVERED', \App\Models\Ledger::documentPrefix(1, 11));
        $this->assertSame('JG/12/26-27', Invoice::generateNumber(1, 'Invoice', 10)['full_number']);
    }

    public function test_credit_and_debit_notes_use_fixed_prefixes_in_both_layouts(): void
    {
        foreach ([0, 1] as $format) {
            $this->settings(['invoice_number_format' => $format, 'billing_number_format' => $format]);
            foreach (['credit_note' => 'CN', 'debit_note' => 'DN'] as $type => $prefix) {
                $expected = $format ? "$prefix/1/26-27" : "$prefix/26-27/1";
                foreach ([10, 11, null] as $ledger) {
                    $this->assertSame($expected, Invoice::generateNumber(1, $type, $ledger)['full_number']);
                }
                $this->assertSame($expected, Invoice::adjustmentNoteNumber(1, $type, $expected)['full_number']);
                $note = Invoice::create(['plant_id' => 1, 'account_id' => 10, 'invoice_type' => $type,
                    'invoice_label' => str_replace('_', ' ', $type), 'prefix' => 'JG/']);
                $this->assertSame($expected, $note->full_number);
                $this->assertSame($format ? "$prefix/2/26-27" : "$prefix/26-27/2", Invoice::generateNumber(1, $type, 11)['full_number']);
            }
        }
    }

    private function createReferenceTables(): void
    {
        Schema::create('mm_payments', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('reference'); $table->softDeletes();
        });
        Schema::create('mm_journal_entries', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('voucher_type');
            $table->string('voucher_number'); $table->boolean('is_deleted')->default(false); $table->softDeletes();
        });
        Schema::create('mm_voucher_types', function (Blueprint $table) {
            $table->id(); $table->string('journal_name'); $table->string('short_code'); $table->string('prefix'); $table->softDeletes();
        });
        Schema::create('mm_expenses', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('paid_through')->nullable();
            $table->date('date')->nullable(); $table->string('ref_no'); $table->timestamps(); $table->softDeletes();
        });
    }

    public function test_payment_formats_padding_and_journal_reservations(): void
    {
        $this->createReferenceTables();
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(8)->with('edit');
        foreach ([
            ['prefix_number_fy', 0, 'PAY/1/26-27'],
            ['prefix_fy_number', 0, 'PAY/26-27/2'],
            ['prefix_compact_fy_number', 0, 'PAY/2627/3'],
            ['prefix_compact_fy_number', 4, 'PAY/2627/0004'],
        ] as [$format, $padding, $expected]) {
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => [
                'payment_number_format' => $format, 'payment_number_padding' => (string) $padding,
            ]]));
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
            $this->assertSame($expected, \App\Models\Payment::generateReferenceNumber(1, null, 'Payment', '2026-11-05'));
            DB::table('mm_payments')->insert(['plant_id' => 1, 'reference' => $expected]);
        }
        DB::table('mm_journal_entries')->insert(['plant_id' => 1, 'voucher_type' => 'PAYMENT', 'voucher_number' => 'PAY/5/26-27']);
        $this->assertSame('PAY/2627/0006', \App\Models\Payment::generateReferenceNumber(1, null, 'Payment', '2026-11-05'));
        $this->assertSame('PAY/2728/0001', \App\Models\Payment::generateReferenceNumber(1, null, 'Payment', '2027-04-01'));
        $this->assertSame('PAY/26-27/1', \App\Models\Payment::generateReferenceNumber(2, null, 'Payment', '2026-11-05'));
        $this->assertSame('REC/26-27/1', \App\Models\Payment::generateReferenceNumber(1, null, 'Receipt', '2026-11-05'));
        $this->assertSame('JG/2627/0001', \App\Models\Payment::generateReferenceNumber(1, 10, 'Payment', '2026-11-05'));
        $this->assertSame('PAY/1/26-27', DB::table('mm_payments')->orderBy('id')->value('reference'));
    }

    public function test_payment_and_receipt_use_prefix_and_reserve_posted_journal_references(): void
    {
        $this->createReferenceTables();
        $this->assertSame('JG/26-27/1', \App\Models\Payment::generateReferenceNumber(1, 10, 'Payment', '2026-11-02'));
        DB::table('mm_payments')->insert(['plant_id' => 1, 'reference' => 'JG/26-27/1']);
        DB::table('mm_journal_entries')->insert(['plant_id' => 1, 'voucher_type' => 'PAYMENT', 'voucher_number' => 'JG/26-27/2']);
        $this->assertSame('JG/26-27/3', \App\Models\Payment::generateReferenceNumber(1, 10, 'Payment', '2026-11-02'));
        $this->assertSame('JG/26-27/3', \App\Models\Payment::generateReferenceNumber(1, 10, 'Receipt', '2026-11-02'));
        $this->assertSame('JG/27-28/1', \App\Models\Payment::generateReferenceNumber(1, 10, 'Payment', '2027-04-01'));
        $this->assertSame('PAY/26-27/1', \App\Models\Payment::generateReferenceNumber(2, 10, 'Payment', '2026-11-02'));
    }

    public function test_expense_creation_uses_paid_through_ledger_and_keeps_saved_reference(): void
    {
        $this->createReferenceTables();
        $expense = \App\Models\Expense::create(['plant_id' => 1, 'paid_through' => 10, 'date' => '2026-11-02']);
        $this->assertSame('JG/26-27/1', $expense->ref_no);
        $this->assertSame('JG/26-27/2', \App\Models\Expense::generateRefNo(1, 10, '2026-11-02'));
        $this->assertSame('PB/26-27/1', \App\Models\Expense::generateRefNo(1, 11, '2026-11-02'));
        $this->assertSame('JG/27-28/1', \App\Models\Expense::generateRefNo(1, 10, '2027-04-01'));
        DB::table('mm_ledgers')->where('id', 10)->update(['description' => 'NEW']);
        $this->assertSame('JG/26-27/1', $expense->fresh()->ref_no);
    }

    public function test_journal_preview_uses_selected_ledger_and_date_with_voucher_fallback(): void
    {
        $this->createReferenceTables();
        DB::table('mm_voucher_types')->insert(['id' => 1, 'journal_name' => 'Journal', 'short_code' => 'JV', 'prefix' => 'OLD']);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['voucher_type' => 'Journal', 'voucher_id' => 1, 'voucher_date' => '2026-11-02', 'account_id' => 10]);
        $response = (new \App\Http\Controllers\JournalEntryController)->generateVoucherNumber($request);
        $this->assertSame('JG/26-27/1', $response->getData(true)['voucher_number']);
        DB::table('mm_journal_entries')->insert(['plant_id' => 1, 'voucher_type' => 'Journal', 'voucher_number' => 'JG/26-27/1']);
        $this->assertSame('JG/26-27/2', \App\Models\JournalEntry::generateVoucherNumber(1, 'Journal', 1, '2026-11-02', 10));
        $this->assertSame('PB/26-27/1', \App\Models\JournalEntry::generateVoucherNumber(1, 'Journal', 1, '2026-11-02', 11));
        $this->assertSame('JG/27-28/1', \App\Models\JournalEntry::generateVoucherNumber(1, 'Journal', 1, '2027-04-01', 10));
        $this->assertSame('OLD/26-27/1', \App\Models\JournalEntry::generateVoucherNumber(2, 'Journal', 1, '2026-11-02', 10));
    }

    public function test_dispatch_numbers_use_customer_ledger_and_separate_plant_and_year_series(): void
    {
        Schema::create('mm_patrons', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->integer('debit_ledger_id');
            $table->string('operational_status')->default('active'); $table->softDeletes();
        });
        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id(); $table->integer('plant_id'); $table->string('prefix'); $table->string('dispatch_no'); $table->softDeletes();
        });
        DB::table('mm_patrons')->insert(['id' => 1, 'plant_id' => 1, 'debit_ledger_id' => 10]);
        $details = \App\Models\Dispatch::nextDocumentNumber(1, 1);
        $this->assertSame(['prefix' => 'JG/26-27/', 'nextNumber' => '1'], $details);
        DB::table('mm_dispatches')->insert(['plant_id' => 1, 'prefix' => $details['prefix'], 'dispatch_no' => '1']);
        $this->assertSame('2', \App\Models\Dispatch::nextDocumentNumber(1, 1)['nextNumber']);
        $this->assertSame('DP/26-27/', \App\Models\Dispatch::nextDocumentNumber(2, 1)['prefix']);
        Carbon::setTestNow('2027-04-01');
        $this->assertSame(['prefix' => 'JG/27-28/', 'nextNumber' => '1'], \App\Models\Dispatch::nextDocumentNumber(1, 1));
    }
}
