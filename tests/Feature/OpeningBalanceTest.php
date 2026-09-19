<?php

namespace Tests\Feature;

use App\Models\{JournalEntry, OpeningBalanceBatch, User};
use App\Services\{OpeningBalanceService, PlantContextService};
use App\Services\Reports\LedgerReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpeningBalanceTest extends TestCase
{
    private OpeningBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // A minimal, isolated SQLite accounting schema. Never touches the configured live database.
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        require base_path('tests/Support/opening-balance-fixture.php');
        $this->service = app(OpeningBalanceService::class);
    }

    private function input(): array
    {
        return ['cutover_date' => '2026-04-01', 'clearing_account_id' => 4, 'notes' => 'Migration', 'lines' => [
            ['account_id' => 1, 'partner_id' => 1, 'side' => 'Dr', 'amount' => '10000.01', 'reference' => 'Customer opening'],
            ['account_id' => 2, 'partner_id' => 2, 'side' => 'Cr', 'amount' => '7000.00'],
            ['account_id' => 3, 'partner_id' => null, 'side' => 'Dr', 'amount' => '25000.00'],
        ]];
    }

    public function test_post_is_balanced_idempotent_and_visible_in_ledger_opening(): void
    {
        $draft = $this->service->save($this->input(), 1, 42, 0);
        $this->assertSame(0, JournalEntry::count());
        $posted = $this->service->post(1, 42, $draft->version);
        $retry = $this->service->post(1, 42, $draft->version);
        $this->assertSame($posted->journal_entry_id, $retry->journal_entry_id);
        $entry = JournalEntry::with('lines')->findOrFail($posted->journal_entry_id);
        $this->assertSame('2026-03-31', $entry->voucher_date->toDateString());
        $this->assertSame('35000.0100', $entry->total_debit);
        $this->assertSame($entry->total_debit, $entry->total_credit);
        $this->assertCount(4, $entry->lines);
        $this->assertSame('28000.0100', $entry->lines->firstWhere('account_id', 4)->credit_amount);
        $this->assertSame('Patron', $entry->lines->firstWhere('account_id', 1)->partner_type);
        $report = app(LedgerReportService::class)->generate(['id' => 1, 'start' => '2026-04-01', 'end' => '2026-04-30']);
        $this->assertEquals(10000.01, $report['opening_balance']);
        $this->assertCount(0, $report['transactions']);
        $filtered = app(LedgerReportService::class)->generate(['id' => 1, 'start' => '2026-04-01', 'end' => '2026-04-30', 'voucher_type_filter' => 'PAYMENT']);
        $this->assertEquals(10000.01, $filtered['opening_balance']);
    }

    public function test_reverse_and_repost_preserves_audit_and_does_not_duplicate_balance(): void
    {
        $draft = $this->service->save($this->input(), 1, 42, 0);
        $posted = $this->service->post(1, 42, $draft->version);
        $reopened = $this->service->reverse(1, 42, $posted->version, 'Correct customer amount');
        $this->assertSame('DRAFT', $reopened->status);
        $this->assertEquals(0, DB::table('mm_journal_entry_lines')->where('account_id', 1)->selectRaw('SUM(debit_amount-credit_amount) as balance')->value('balance'));
        $input = $this->input();
        $input['lines'][0]['amount'] = '12000.00';
        $draft = $this->service->save($input, 1, 42, $reopened->version);
        $this->service->post(1, 42, $draft->version);
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, JournalEntry::whereNotNull('reversal_of_id')->count());
        $this->assertEquals(12000, DB::table('mm_journal_entry_lines')->where('account_id', 1)->selectRaw('SUM(debit_amount-credit_amount) as balance')->value('balance'));
    }

    public function test_balanced_trial_balance_needs_no_clearing_line(): void
    {
        $input = $this->input();
        $input['clearing_account_id'] = null;
        $input['lines'][] = ['account_id' => 5, 'partner_id' => null, 'side' => 'Cr', 'amount' => '28000.01'];
        $draft = $this->service->save($input, 1, 42, 0);
        $posted = $this->service->post(1, 42, $draft->version);
        $this->assertSame(4, JournalEntry::find($posted->journal_entry_id)->lines()->count());
        $this->assertDatabaseMissing('mm_journal_entry_lines', ['account_id' => 4]);
    }

    public function test_invalid_rows_cannot_be_saved(): void
    {
        $cases = [];
        $data = $this->input(); $data['lines'][0]['account_id'] = 6; $cases[] = $data;
        $data = $this->input(); $data['lines'][0]['partner_id'] = 3; $cases[] = $data;
        $data = $this->input(); $data['lines'][] = $data['lines'][0]; $cases[] = $data;
        $data = $this->input(); $data['lines'][] = ['account_id' => 1, 'partner_id' => null, 'side' => 'Dr', 'amount' => '10000']; $cases[] = $data;
        foreach (['0', '-10', '0.001', '1e3', 'NaN', '1,000', '1000000000000'] as $badAmount) {
            $data = $this->input(); $data['lines'][0]['amount'] = $badAmount; $cases[] = $data;
        }
        foreach ($cases as $case) {
            try {
                $this->service->save($case, 1, 42, 0);
                $this->fail('Invalid opening accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, OpeningBalanceBatch::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_missing_clearing_ledger_blocks_post_atomically(): void
    {
        $input = $this->input(); $input['clearing_account_id'] = null;
        $draft = $this->service->save($input, 1, 42, 0);
        try { $this->service->post(1, 42, $draft->version); $this->fail('Unbalanced journal accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('clearing_account_id', $e->errors()); }
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame('DRAFT', $draft->fresh()->status);
    }

    public function test_stale_save_and_edit_of_posted_setup_are_rejected(): void
    {
        $draft = $this->service->save($this->input(), 1, 42, 0);
        try { $this->service->save($this->input(), 1, 42, 0); $this->fail('Stale save accepted'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $posted = $this->service->post(1, 42, $draft->version);
        try { $this->service->save($this->input(), 1, 42, $posted->version); $this->fail('Posted edit accepted'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
    }

    public function test_prior_unposted_invoice_blocks_duplicate_opening(): void
    {
        DB::table('mm_invoices')->insert(['plant_id' => 1, 'partner_id' => 1, 'invoice_type' => 'sales', 'invoice_date' => '2026-03-15']);
        $draft = $this->service->save($this->input(), 1, 42, 0);
        try { $this->service->post(1, 42, $draft->version); $this->fail('Historical invoice ignored'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('lines', $e->errors()); }
        $this->assertSame(0, JournalEntry::count());
    }

    private function httpUser(bool $admin = true): void
    {
        $this->withoutMiddleware();
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 42;
        $user->shouldReceive('isSystemAdmin')->andReturn($admin);
        $user->shouldReceive('getAllPermissions')->andReturn(collect());
        $this->actingAs($user);
    }

    public function test_csv_preview_resolves_codes_without_writing_balances(): void
    {
        $this->httpUser();
        $csv = "\xEF\xBB\xBFledger_code,patron_code,side,amount,reference\r\nL1,C1,Dr,100.25,\"Old, balance\"\r\nL3,,Dr,99.75,Bank\r\n";
        $response = $this->postJson(route('opening-balances.import'), [
            'cutover_date' => '2026-04-01', 'file' => UploadedFile::fake()->createWithContent('opening.csv', $csv),
        ]);
        $response->assertOk()->assertJsonPath('lines.0.partner_id', 1)->assertJsonPath('lines.0.reference', 'Old, balance')->assertJsonPath('lines.1.partner_id', null);
        $this->assertSame(0, OpeningBalanceBatch::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_csv_rejects_unknown_plant_codes_and_bad_headers(): void
    {
        $this->httpUser();
        foreach (["bad,header\n", "ledger_code,patron_code,side,amount,reference\nOTHER,,Dr,100,\n"] as $csv) {
            $this->postJson(route('opening-balances.import'), ['cutover_date' => '2026-04-01', 'file' => UploadedFile::fake()->createWithContent('opening.csv', $csv)])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
    }

    public function test_missing_permissions_and_plant_are_rejected(): void
    {
        $this->httpUser(false);
        $this->putJson(route('opening-balances.save'), $this->input() + ['version' => 0])->assertForbidden();
        $this->httpUser();
        session()->forget('active_plant_id');
        $this->putJson(route('opening-balances.save'), $this->input() + ['version' => 0])->assertForbidden();
    }

    public function test_opening_journals_cannot_be_deleted_through_general_journal(): void
    {
        $draft = $this->service->save($this->input(), 1, 42, 0);
        $posted = $this->service->post(1, 42, $draft->version);
        $this->httpUser();
        $this->deleteJson(route('journalentries.destroy', $posted->journal_entry_id))->assertConflict();
        $this->assertSame(1, JournalEntry::count());
    }
}
