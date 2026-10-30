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
}
