<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:12:44 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Ledger, Patron, Plant, User};
use App\Services\Reports\{CustomerReceiptPaymentReportService, ExcelExportService, ReportServiceFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CustomerReceiptPaymentReportTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Patron $customer;
    private Ledger $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $this->customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Report Customer']);
        $this->account = Ledger::factory()->create(['plant_id' => $this->plant->id]);
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession([
            'active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    private function payment(string $type, float $amount, array $extra = []): int
    {
        return DB::table('mm_payments')->insertGetId(array_merge([
            'plant_id' => $this->plant->id, 'patron_id' => $this->customer->id,
            'ledger_id' => $this->account->id, 'transaction_date' => '2026-10-10',
            'transaction_type' => $type, 'amount' => $amount, 'partner_type' => 'Customer',
            'status' => 'completed', 'reference' => 'TEST-REF', 'transaction_mode' => 'Bank',
        ], $extra));
    }

    private function manual(float $amount, array $entryExtra = [], array $lineExtra = []): void
    {
        $entryId = DB::table('mm_journal_entries')->insertGetId(array_merge([
            'plant_id' => $this->plant->id, 'entity_id' => $this->plant->entity_id,
            'voucher_type' => 'RECEIPT', 'voucher_number' => 'MANUAL-'.uniqid(),
            'voucher_date' => '2026-10-10', 'posting_date' => '2026-10-10', 'is_status' => 'POSTED',
        ], $entryExtra));
        DB::table('mm_journal_entry_lines')->insert(array_merge([
            'journal_entry_id' => $entryId, 'plant_id' => $this->plant->id,
            'account_id' => $this->account->id, 'partner_type' => 'Patron',
            'partner_id' => $this->customer->id, 'credit_amount' => $amount, 'debit_amount' => 0,
        ], $lineExtra));
    }

    private function report(array $extra = []): array
    {
        return app(CustomerReceiptPaymentReportService::class)->generate(array_merge([
            'start' => '2026-10-01 00:00:00', 'end' => '2026-10-31 23:59:59',
        ], $extra));
    }

    public function test_customer_totals_include_receipts_and_refunds_without_writing_journals(): void
    {
        $this->payment('receipt', 120.15);
        $this->payment('receipt', 20.25);
        $this->payment('payment', 30.10);
        $before = DB::table('mm_journal_entries')->count();
        $data = $this->report();
        $this->assertEquals(140.40, $data['total_receipt']);
        $this->assertEquals(30.10, $data['total_payment']);
        $this->assertEquals(110.30, $data['net_received']);
        $this->assertCount(1, $data['transactions']);
        $this->assertCount(3, $data['details']);
        $this->assertSame($before, DB::table('mm_journal_entries')->count());
    }

    public function test_filters_exclude_other_plants_dates_failed_pending_and_deleted_payments(): void
    {
        $this->payment('receipt', 10);
        $this->payment('receipt', 100, ['status' => 'pending']);
        $this->payment('receipt', 100, ['status' => 'failed']);
        $this->payment('receipt', 100, ['deleted_at' => now()]);
        $this->payment('receipt', 100, ['transaction_date' => '2026-09-30']);
        $other = Plant::factory()->create();
        $this->payment('receipt', 100, ['plant_id' => $other->id]);
        $this->assertEquals(10, $this->report()['total_receipt']);
        $this->assertCount(0, $this->report(['patron_id' => 999999])['transactions']);
    }

    public function test_customer_filter_and_supplier_exclusion_include_inactive_customer_history(): void
    {
        $this->payment('receipt', 10);
        $supplier = Patron::factory()->create(['plant_id' => $this->plant->id, 'patron_type' => ['Vendor']]);
        $this->payment('receipt', 100, ['patron_id' => $supplier->id]);
        $this->customer->update(['patron_type' => ['Customer', 'Vendor'], 'operational_status' => 'inactive']);
        $this->payment('payment', 100, ['partner_type' => 'Vendor']);
        $data = $this->report(['patron_id' => $this->customer->id]);
        $this->assertEquals(10, $data['total_receipt']);
        $this->assertEquals(0, $data['total_payment']);
        $this->assertCount(1, $data['details']);
    }

    public function test_posted_manual_vouchers_are_counted_once_and_drafts_and_deleted_lines_are_excluded(): void
    {
        $this->payment('receipt', 10);
        $this->manual(10, ['ref_module' => 'payment']);
        $this->manual(25);
        $this->manual(100, ['is_status' => 'DRAFT']);
        $this->manual(100, [], ['deleted_at' => now()]);
        $this->manual(100, ['is_deleted' => 1]);
        $this->manual(0, ['voucher_type' => 'PAYMENT'], ['debit_amount' => 5]);
        $data = $this->report();
        $this->assertEquals(35, $data['total_receipt']);
        $this->assertEquals(5, $data['total_payment']);
        $this->assertCount(3, $data['details']);
    }

    public function test_empty_report_has_zero_totals_and_factory_resolves_service(): void
    {
        $this->assertInstanceOf(CustomerReceiptPaymentReportService::class,
            app(ReportServiceFactory::class)->make('customer_receipt_payment'));
        $data = $this->report();
        $this->assertEquals(0, $data['total_receipt']);
        $this->assertEquals(0, $data['total_payment']);
        $this->assertSame([], $data['transactions']);
        $this->assertSame([], $data['details']);
    }

    public function test_report_endpoint_honors_report_permissions(): void
    {
        $this->payment('receipt', 45);
        $url = route('reports.generate', ['type' => 'customer_receipt_payment',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
        $this->getJson($url)->assertOk()->assertJsonPath('total_receipt', 45);
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
    }

    public function test_excel_export_contains_customer_totals_and_voucher_details(): void
    {
        $this->payment('receipt', 45);
        $book = app(ExcelExportService::class)->generateExcelReport('customer_receipt_payment',
            '2026-10-01', '2026-10-31', $this->report());
        $rows = $book->getActiveSheet()->toArray(null, true, false);
        $customerRows = array_values(array_filter($rows, fn ($row) => $row[0] === 'Report Customer'));
        $this->assertCount(1, $customerRows);
        $this->assertEquals(45, $customerRows[0][1]);
        $this->assertTrue(collect($rows)->contains(fn ($row) => in_array('TEST-REF', $row, true)));
        $book->disconnectWorksheets();
    }

    public function test_view_permission_allows_browser_report_without_export_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Receipt Report Viewer', 'guard_name' => 'web'], ['code' => 'RECEIPT_REPORT_VIEWER']);
        DB::table('mm_entity_users')->insert([
            'user_id' => $user->id, 'entity_id' => $this->plant->entity_id, 'plant_id' => $this->plant->id,
            'role_id' => $role->id,
        ]);
        $user->givePermissionTo('REPORT_CUSTOMER_RECEIPT_PAYMENT.VIEW');
        $this->actingAs($user);
        $filters = ['type' => 'customer_receipt_payment', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
        $this->getJson(route('reports.generate', $filters + ['export' => 'view']))->assertOk();
        $this->getJson(route('reports.generate', $filters + ['export' => 'excel']))->assertForbidden();
    }

    public function test_pdf_template_renders_the_customer_summary_and_totals(): void
    {
        $this->payment('receipt', 45);
        $html = view('reports.generic_report', array_merge($this->report(), [
            'plant' => $this->plant, 'patron' => null, 'target_name' => 'Customer Receipt/Payment Amount',
            'start' => '01-10-2026', 'end' => '31-10-2026',
        ]))->render();
        $this->assertStringContainsString('Report Customer', $html);
        $this->assertStringContainsString('Receipt Amount', $html);
        $this->assertStringContainsString('45.00', $html);
    }
}
