<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:46:40 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Patron, Plant, User};
use App\Services\Reports\{CreditDebitNoteRegisterService, ExcelExportService, ReportServiceFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreditDebitNoteRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Patron $partner;
    private int $sourceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $this->partner = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Register Partner']);
        $this->sourceId = DB::table('mm_invoices')->insertGetId([
            'plant_id' => $this->plant->id, 'partner_id' => $this->partner->id,
            'invoice_type' => 'Invoice', 'prefix' => 'INV/', 'invoice_number' => 'INV/001',
            'invoice_date' => '2026-09-25', 'total_amount' => 1000, 'status' => 'approved',
        ]);
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession([
            'active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    private function note(string $type = 'credit_note', array $extra = []): int
    {
        return DB::table('mm_invoices')->insertGetId(array_merge([
            'plant_id' => $this->plant->id, 'partner_id' => $this->partner->id, 'ref_id' => $this->sourceId,
            'ref_title' => 'INV/001', 'invoice_type' => $type, 'prefix' => $type === 'credit_note' ? 'CN/' : 'DN/',
            'invoice_number' => uniqid(), 'invoice_date' => '2026-10-15',
            'subtotal' => 100, 'discount_total' => 10, 'tax_amount' => 16.2,
            'total_amount' => 106, 'round_off' => -0.2, 'status' => 'approved', 'notes' => 'Adjustment reason',
        ], $extra));
    }

    private function report(array $extra = []): array
    {
        return app(CreditDebitNoteRegisterService::class)->generate(array_merge([
            'start' => '2026-10-01 00:00:00', 'end' => '2026-10-31 23:59:59',
        ], $extra));
    }

    public function test_both_note_types_show_stored_totals_original_document_and_separate_amounts(): void
    {
        $this->note();
        $this->note('debit_note', ['total_amount' => 50]);
        $data = $this->report();
        $this->assertCount(2, $data['transactions']);
        $this->assertEquals(106, $data['credit_note_amount']);
        $this->assertEquals(50, $data['debit_note_amount']);
        $this->assertEquals(156, $data['total_amount']);
        $this->assertSame('INV/001', $data['transactions'][0]['source_number']);
        $this->assertSame('2026-09-25', $data['transactions'][0]['source_date']);
        $this->assertSame('Register Partner', $data['transactions'][0]['partner_name']);
        $this->assertEquals(32.4, $data['totals']['tax_amt']);
    }

    public function test_note_type_date_partner_and_plant_filters(): void
    {
        $this->note();
        $this->note('debit_note');
        $this->note('credit_note', ['invoice_date' => '2026-09-30']);
        $otherPlant = Plant::factory()->create();
        $this->note('credit_note', ['plant_id' => $otherPlant->id]);
        $this->assertCount(1, $this->report(['voucher_type_filter' => 'CREDIT_NOTE'])['transactions']);
        $this->assertCount(1, $this->report(['voucher_type_filter' => 'DEBIT_NOTE'])['transactions']);
        $this->assertCount(0, $this->report(['patron_id' => 999999])['transactions']);
        $this->assertCount(2, $this->report()['transactions']);
    }

    public function test_cancelled_deleted_and_reversed_notes_are_excluded(): void
    {
        $this->note();
        foreach (['cancelled', 'void', 'reversed'] as $status) $this->note('credit_note', ['status' => $status]);
        $this->note('credit_note', ['deleted_at' => now()]);
        $this->assertEquals(106, $this->report()['total_amount']);
        $this->assertCount(1, $this->report()['transactions']);
    }

    public function test_original_reference_survives_soft_deletion_and_cross_plant_sources_are_not_exposed(): void
    {
        $this->note();
        DB::table('mm_invoices')->where('id', $this->sourceId)->update(['deleted_at' => now()]);
        $this->assertSame('INV/001', $this->report()['transactions'][0]['source_number']);
        $otherPlant = Plant::factory()->create();
        DB::table('mm_invoices')->where('id', $this->sourceId)->update([
            'plant_id' => $otherPlant->id, 'invoice_number' => 'PRIVATE',
        ]);
        $data = $this->report();
        $this->assertSame('INV/001', $data['transactions'][0]['source_number']);
        $this->assertSame('', $data['transactions'][0]['source_date']);
    }

    public function test_empty_result_and_factory_registration(): void
    {
        $this->assertInstanceOf(CreditDebitNoteRegisterService::class,
            app(ReportServiceFactory::class)->make('credit_debit_note_register'));
        $data = $this->report();
        $this->assertSame([], $data['transactions']);
        $this->assertEquals(0, $data['total_amount']);
    }

    public function test_report_endpoint_and_permissions(): void
    {
        $this->note();
        $url = route('reports.generate', ['type' => 'credit_debit_note_register',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'voucher_type_filter' => 'CREDIT_NOTE']);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'transactions')->assertJsonPath('transactions.0.note_type', 'Credit Note');
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
    }

    public function test_excel_and_pdf_render_the_register_amounts(): void
    {
        $this->note();
        $data = $this->report();
        $book = app(ExcelExportService::class)->generateExcelReport('credit_debit_note_register', '2026-10-01', '2026-10-31', $data);
        $rows = $book->getActiveSheet()->toArray(null, true, false);
        $totals = array_values(array_filter($rows, fn ($row) => $row[0] === 'Total'));
        $this->assertEquals(106, $totals[0][10]);
        $this->assertTrue(collect($rows)->contains(fn ($row) => in_array('INV/001', $row, true)));
        $book->disconnectWorksheets();
        $html = view('reports.generic_report', array_merge($data, [
            'plant' => $this->plant, 'patron' => null, 'target_name' => 'Credit Note & Debit Note Register',
            'start' => '01-10-2026', 'end' => '31-10-2026',
        ]))->render();
        $this->assertStringContainsString('Register Partner', $html);
        $this->assertStringContainsString('106.00', $html);
    }
}
