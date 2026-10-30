<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 17:51:28 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\OpeningBalanceAuditLog;
use App\Services\IndividualOpeningBalanceService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class OpeningBalanceNarrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        require base_path('tests/Support/opening-balance-fixture.php');
        foreach ([
            '2026_09_07_190000_add_narration_label_to_mm_journal_entries_table.php',
            '2026_09_22_140000_create_opening_balance_audit_logs.php',
            '2026_09_22_150000_individual_opening_balances.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->travelTo(now()->setDate(2026, 10, 30));
    }

    public static function balanceCases(): array
    {
        return [
            'patron debit' => ['patron', 'Dr', 1, 'Party'],
            'patron credit' => ['patron', 'Cr', 2, 'Party'],
            'ledger debit' => ['ledger', 'Dr', 3, 'Ledger'],
            'ledger credit' => ['ledger', 'Cr', 3, 'Ledger'],
        ];
    }

    public function test_opening_journal_can_be_deleted_and_its_balance_can_be_created_again(): void
    {
        $service = app(IndividualOpeningBalanceService::class);
        $input = ['version' => 0, 'balance_type' => 'ledger', 'account_id' => 3,
            'cutover_date' => '2026-04-01', 'side' => 'Dr', 'amount' => '100.00',
            'clearing_account_id' => 4, 'reason' => 'Initial opening balance'];
        $record = $service->save($input, 1, 42);
        JournalEntry::findOrFail($record->journal_entry_id)->delete();

        $this->assertSoftDeleted('mm_opening_balance_batches', ['id' => $record->id]);
        $this->assertDatabaseHas('mm_opening_balance_batches', ['id' => $record->id, 'active_key' => null, 'status' => 'REMOVED']);
        $this->assertDatabaseHas('mm_journal_entries', ['id' => $record->journal_entry_id, 'is_deleted' => 1]);
        $this->assertSame(0, DB::table('mm_journal_entry_lines')->where('journal_entry_id', $record->journal_entry_id)->whereNull('deleted_at')->count());

        $new = $service->save($input, 1, 42);
        $this->assertNotEquals($record->id, $new->id);
        $this->assertSame('POSTED', $new->status);

        $audit = OpeningBalanceAuditLog::where('batch_id', $record->id)->firstOrFail();
        $audit->update(['reason' => 'Updated record']);
        $this->assertSame('Updated record', $audit->fresh()->reason);
        $audit->delete();
        $this->assertDatabaseMissing('mm_opening_balance_audit_logs', ['id' => $audit->id]);
    }

    #[DataProvider('balanceCases')]
    public function test_edit_replacement_stores_header_and_line_narration(string $type, string $side, int $account, string $name): void
    {
        $service = app(IndividualOpeningBalanceService::class);
        $input = [
            'version' => 0, 'balance_type' => $type, 'account_id' => 3,
            'cutover_date' => '2026-04-01', 'side' => 'Dr', 'amount' => '100.00',
            'clearing_account_id' => 4, 'reason' => 'Initial opening balance',
            'reference' => 'Old reference',
        ];
        if ($type === 'patron') $input['patron_id'] = 1;
        $original = $service->save($input, 1, 42);
        // Reproduce editing an older opening journal without narration metadata.
        DB::table('mm_journal_entries')->where('id', $original->journal_entry_id)->update(['narration_label' => null]);
        DB::table('mm_journal_entry_lines')->where('journal_entry_id', $original->journal_entry_id)
            ->update(['narration_name' => null, 'narration_label' => null]);

        $updated = $service->save(array_replace($input, [
            'record_id' => $original->id, 'version' => $original->version,
            'side' => $side, 'amount' => '250.00', 'reference' => 'Updated reference',
            'reason' => 'Correct opening amount',
        ]), 1, 42);

        $entry = JournalEntry::with('lines')->findOrFail($updated->journal_entry_id);
        $this->assertSame('Opening Balance', $entry->narration_label);
        $this->assertSame('Individual opening balance. Correct opening amount', $entry->narration);
        $this->assertCount(2, $entry->lines);
        $primary = $entry->lines->firstWhere('account_id', $account);
        $offset = $entry->lines->firstWhere('account_id', 4);
        $this->assertSame($name, $primary->narration_name);
        $this->assertSame('Opening Balance Clearing', $offset->narration_name);
        $this->assertSame('Updated reference', $primary->line_narration);
        foreach ($entry->lines as $line) $this->assertSame('Opening Balance', $line->narration_label);
        $this->assertEquals(250, $entry->lines->sum('debit_amount'));
        $this->assertEquals(250, $entry->lines->sum('credit_amount'));
        $this->assertDatabaseHas('mm_journal_entries', ['id' => $original->journal_entry_id, 'is_deleted' => 1]);
        $audit = OpeningBalanceAuditLog::where('batch_id', $updated->id)->sole();
        $this->assertSame('REPLACE', $audit->action);
        $this->assertSame('Opening Balance', $audit->journal['narration_label']);
        $this->assertSame($name, $audit->journal['lines'][0]['narration_name']);
    }
}
