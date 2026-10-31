<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 17:28:30 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\Reports\LedgerReportService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class LedgerReportOpeningBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        require base_path('tests/Support/opening-balance-fixture.php');
    }

    private function journal(string $date, float $amount, int $patron = 1, int $plant = 1, bool $opening = true): int
    {
        $id = DB::table('mm_journal_entries')->insertGetId([
            'plant_id' => $plant, 'entity_id' => 10, 'voucher_type' => $opening ? 'OPENING' : 'JOURNAL',
            'voucher_number' => uniqid('TEST-'), 'ref_module' => $opening ? 'opening_balance' : null,
            'voucher_date' => $date, 'posting_date' => $date, 'is_status' => 'POSTED',
        ]);
        DB::table('mm_journal_entry_lines')->insert([
            ['journal_entry_id' => $id, 'plant_id' => $plant, 'account_id' => 1,
                'partner_type' => 'Patron', 'partner_id' => $patron,
                'debit_amount' => max(0, $amount), 'credit_amount' => max(0, -$amount)],
            ['journal_entry_id' => $id, 'plant_id' => $plant, 'account_id' => 4,
                'partner_type' => null, 'partner_id' => null,
                'debit_amount' => max(0, -$amount), 'credit_amount' => max(0, $amount)],
        ]);
        return $id;
    }

    private function report(array $filters = []): array
    {
        return app(LedgerReportService::class)->generate($filters + [
            'patron_id' => 1, 'start' => '2026-04-01 00:00:00', 'end' => '2027-03-31 23:59:59',
        ]);
    }

    public function test_financial_year_patron_opening_does_not_cancel_against_its_offset(): void
    {
        $this->journal('2026-03-31', 1000);
        $this->journal('2026-03-30', -200, opening: false);
        $this->journal('2026-03-31', 500, patron: 2);
        $this->journal('2026-03-31', 700, plant: 2);
        $deleted = $this->journal('2026-03-31', 900);
        DB::table('mm_journal_entries')->where('id', $deleted)->update(['deleted_at' => now(), 'is_deleted' => 1]);
        $this->journal('2026-04-02', -50, opening: false);

        $report = $this->report();
        $this->assertEquals(800, $report['opening_balance']);
        $this->assertCount(1, $report['transactions']);
        $this->assertEquals(50, $report['transactions'][0]['credit']);
        $this->assertEquals(800, $this->report(['voucher_type_filter' => 'PAYMENT'])['opening_balance']);
        $this->assertEquals(800, $this->report(['id' => 1])['opening_balance']);
        $this->assertEquals(-800, $this->report(['id' => 4])['opening_balance']);
    }

    public function test_credit_opening_and_same_day_opening_are_included_once(): void
    {
        $this->journal('2026-04-01', -250);
        $report = $this->report();
        $this->assertEquals(-250, $report['opening_balance']);
        $this->assertCount(0, $report['transactions']);
    }

    public function test_deleted_patron_lines_do_not_qualify_an_offset_ledger(): void
    {
        $id = $this->journal('2026-03-31', 1000);
        DB::table('mm_journal_entry_lines')->where('journal_entry_id', $id)->where('partner_id', 1)
            ->update(['is_deleted' => 1]);
        $this->assertEquals(0, $this->report(['id' => 4])['opening_balance']);
    }
    public function test_opening_inside_report_period_is_not_lost(): void
    {
        $this->journal('2026-04-01', 1000);
        $this->journal('2026-04-02', -150, opening: false);
        $report = $this->report(['start' => '2026-03-01']);
        $this->assertEquals(0, $report['opening_balance']);
        $this->assertCount(2, $report['transactions']);
        $this->assertEquals('2026-04-01', $report['transactions'][0]['date']);
        $this->assertEquals(850, $report['closing_balance']);
        $this->assertEquals(850, $this->report()['closing_balance']);
    }

    public function test_selected_shared_ledger_does_not_include_another_patrons_line(): void
    {
        $id = $this->journal('2026-03-31', 1000);
        DB::table('mm_journal_entry_lines')->insert([
            'journal_entry_id' => $id, 'plant_id' => 1, 'account_id' => 1,
            'partner_type' => 'Patron', 'partner_id' => 2, 'debit_amount' => 500, 'credit_amount' => 0,
        ]);
        $this->assertEquals(1000, $this->report(['id' => 1])['opening_balance']);
    }

    public function test_real_individual_opening_creation_replacement_removal_and_cutover_dates(): void
    {
        foreach (['2026_09_07_190000_add_narration_label_to_mm_journal_entries_table.php',
            '2026_09_22_140000_create_opening_balance_audit_logs.php', '2026_09_22_150000_individual_opening_balances.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->travelTo(now()->setDate(2026, 10, 30));
        $service = app(\App\Services\IndividualOpeningBalanceService::class);
        $input = ['version' => 0, 'balance_type' => 'patron', 'patron_id' => 1,
            'cutover_date' => '2026-04-01', 'side' => 'Dr', 'amount' => '1000.00',
            'clearing_account_id' => 4, 'reason' => 'Initial balance'];
        $record = $service->save($input, 1, 42);
        $this->assertEquals(1000, $this->report()['opening_balance']);
        $priorYear = $this->report(['start' => '2026-03-01', 'end' => '2026-03-31']);
        $this->assertEquals(0, $priorYear['opening_balance']);
        $this->assertCount(0, $priorYear['transactions']);
        $acrossCutover = $this->report(['start' => '2026-03-01']);
        $this->assertCount(1, $acrossCutover['transactions']);
        $this->assertEquals('2026-04-01', $acrossCutover['transactions'][0]['date']);
        $this->assertEquals(1000, $acrossCutover['closing_balance']);
        $updated = $service->save([...$input, 'record_id' => $record->id, 'version' => $record->version,
            'side' => 'Cr', 'amount' => '250.50', 'reason' => 'Corrected balance'], 1, 42);
        $this->assertEquals(-250.5, $this->report()['opening_balance']);
        $this->assertEquals(-250.5, $this->report()['closing_balance']);
        $this->assertEquals(-250.5, $this->report(['id' => 2])['opening_balance']);
        $this->assertEquals(250.5, $this->report(['id' => 4])['opening_balance']);
        $service->remove(1, 42, $updated->id, $updated->version, 'Remove test balance');
        $this->assertEquals(0, $this->report()['opening_balance']);
        $this->assertCount(0, $this->report(['start' => '2026-03-01'])['transactions']);
    }

    public function test_legacy_reversals_net_once_and_voucher_filter_preserves_opening(): void
    {
        $this->journal('2026-03-31', 1000);
        $reversal = $this->journal('2026-03-31', -1000);
        DB::table('mm_journal_entries')->where('id', $reversal)->update(['ref_module' => 'opening_balance_reversal']);
        $this->journal('2026-03-31', 250);
        $payment = $this->journal('2026-04-02', -50, opening: false);
        DB::table('mm_journal_entries')->where('id', $payment)->update(['voucher_type' => 'PAYMENT']);
        $this->journal('2026-04-03', 100, opening: false);
        $report = $this->report(['voucher_type_filter' => 'PAYMENT']);
        $this->assertEquals(250, $report['opening_balance']);
        $this->assertCount(1, $report['transactions']);
        $this->assertEquals(200, $report['transactions'][0]['running_balance']);
        $this->assertEquals(200, $report['closing_balance']);
        $this->assertEquals(0, $report['total_debit']);
        $this->assertEquals(50, $report['total_credit']);
        $this->assertEquals(300, $this->report()['closing_balance']);
    }

    public function test_ledger_only_balances_and_empty_reports_have_consistent_totals(): void
    {
        $empty = $this->report();
        $this->assertEquals(0, $empty['opening_balance']);
        $this->assertEquals(0, $empty['closing_balance']);
        $this->journal('2026-03-31', 1000);
        $this->journal('2026-03-31', 500, patron: 2);
        $this->journal('2026-04-02', -100.25, opening: false);
        $report = $this->report(['id' => 1, 'patron_id' => null]);
        $this->assertEquals(1500, $report['opening_balance']);
        $this->assertEquals(1399.75, $report['closing_balance']);
        $this->assertEquals(100.25, $report['total_credit']);
        $this->assertEquals(1399.75, $report['transactions'][0]['running_balance']);
        $clearing = $this->report(['id' => 4, 'patron_id' => null]);
        $this->assertEquals(-1500, $clearing['opening_balance']);
        $this->assertEquals(-1399.75, $clearing['closing_balance']);
        $this->assertEquals(0, $this->report(['patron_id' => null])['closing_balance']);
    }

    public function test_all_ledgers_shows_opening_debits_and_credits_even_when_net_is_zero(): void
    {
        $this->journal('2026-03-31', 11000);
        $all = $this->report(['patron_id' => null]);
        $this->assertEquals(0, $all['opening_balance']);
        $this->assertEquals(11000, $all['opening_debit']);
        $this->assertEquals(11000, $all['opening_credit']);
        $this->assertSame('all_ledgers', $all['balance_scope']);
        foreach ([['patron_id' => 1], ['patron_id' => null, 'id' => 1]] as $filters) {
            $scoped = $this->report($filters);
            $this->assertEquals(11000, $scoped['opening_balance']);
            $this->assertEquals(11000, $scoped['opening_debit']);
            $this->assertEquals(0, $scoped['opening_credit']);
        }
        $offset = $this->report(['patron_id' => null, 'id' => 4]);
        $this->assertEquals(-11000, $offset['opening_balance']);
        $this->assertEquals(0, $offset['opening_debit']);
        $this->assertEquals(11000, $offset['opening_credit']);
    }

}
