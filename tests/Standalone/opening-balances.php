<?php

// php tests/Standalone/opening-balances.php — no PHPUnit dependency, SQLite memory only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array', 'cache.default' => 'array']);
Illuminate\Support\Facades\DB::purge('sqlite');
require __DIR__.'/../Support/opening-balance-fixture.php';

use App\Models\{JournalEntry, OpeningBalanceBatch};
use App\Services\OpeningBalanceService;
use App\Services\Reports\LedgerReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

$checks = 0;
function check($expected, $actual, string $label): void {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException($label.': '.var_export($actual, true));
    $checks++;
}
function rejects(callable $call, string $class, string $label): void {
    global $checks;
    try { $call(); } catch (Throwable $e) {
        if ($e instanceof $class) { $checks++; return; }
        throw $e;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}

$service = app(OpeningBalanceService::class);
$input = ['cutover_date' => '2026-04-01', 'clearing_account_id' => 4, 'notes' => 'Legacy balances', 'lines' => [
    ['account_id' => 1, 'partner_id' => 1, 'side' => 'Dr', 'amount' => '10000.01'],
    ['account_id' => 2, 'partner_id' => 2, 'side' => 'Cr', 'amount' => '7000.00'],
    ['account_id' => 3, 'partner_id' => null, 'side' => 'Dr', 'amount' => '25000.00'],
]];
foreach (['0', '-10', '0.001', '1e3', 'NaN', '1,000', '1000000000000'] as $amount) {
    $bad = $input; $bad['lines'][0]['amount'] = $amount;
    rejects(fn () => $service->save($bad, 1, 42, 0), ValidationException::class, 'Invalid amount');
}
$bad = $input; $bad['lines'][0]['account_id'] = 6;
rejects(fn () => $service->save($bad, 1, 42, 0), ValidationException::class, 'Cross plant ledger');
$bad = $input; $bad['lines'][0]['partner_id'] = 3;
rejects(fn () => $service->save($bad, 1, 42, 0), ValidationException::class, 'Cross plant patron');
$bad = $input; $bad['lines'][] = $bad['lines'][0];
rejects(fn () => $service->save($bad, 1, 42, 0), ValidationException::class, 'Duplicate patron');
$bad = $input; $bad['lines'][] = ['account_id' => 1, 'partner_id' => null, 'side' => 'Dr', 'amount' => '10000'];
rejects(fn () => $service->save($bad, 1, 42, 0), ValidationException::class, 'Duplicate control ledger');
check(0, OpeningBalanceBatch::count(), 'Invalid drafts not persisted');
$draft = $service->save($input, 1, 42, 0);
check(0, JournalEntry::count(), 'Draft has no accounting effects');
rejects(fn () => $service->save($input, 1, 42, 0), HttpException::class, 'Stale save');
$posted = $service->post(1, 42, $draft->version);
check($posted->journal_entry_id, $service->post(1, 42, $draft->version)->journal_entry_id, 'Idempotent posting');
$entry = JournalEntry::with('lines')->findOrFail($posted->journal_entry_id);
check('2026-03-31', $entry->voucher_date->toDateString(), 'Migration journal date');
check('35000.0100', $entry->total_debit, 'Exact total debit');
check($entry->total_debit, $entry->total_credit, 'Balanced journal');
check(4, $entry->lines->count(), 'Three balances plus clearing');
check('28000.0100', $entry->lines->firstWhere('account_id', 4)->credit_amount, 'Clearing amount');
check('Patron', $entry->lines->firstWhere('account_id', 1)->partner_type, 'Patron linkage');
$report = app(LedgerReportService::class)->generate(['id' => 1, 'start' => '2026-04-01', 'end' => '2026-04-30']);
check(10000.01, $report['opening_balance'], 'Ledger report opening');
check(0, $report['transactions']->count(), 'No duplicate period activity');
$filtered = app(LedgerReportService::class)->generate(['id' => 1, 'start' => '2026-04-01', 'end' => '2026-04-30', 'voucher_type_filter' => 'PAYMENT']);
check(10000.01, $filtered['opening_balance'], 'Voucher filter retains opening');
rejects(fn () => $service->save($input, 1, 42, $posted->version), HttpException::class, 'Posted setup locked');
rejects(fn () => $entry->delete(), LogicException::class, 'Journal cannot be deleted');
$draft = $service->reverse(1, 42, $posted->version, 'Correct customer amount');
check(0.0, (float) DB::table('mm_journal_entry_lines')->where('account_id', 1)->selectRaw('SUM(debit_amount-credit_amount) as balance')->value('balance'), 'Reversal cancels patron opening');
$input['lines'][0]['amount'] = '12000.00';
$draft = $service->save($input, 1, 42, $draft->version);
$posted = $service->post(1, 42, $draft->version);
check(3, JournalEntry::count(), 'Original, reversal and replacement preserved');
check(12000.0, (float) DB::table('mm_journal_entry_lines')->where('account_id', 1)->selectRaw('SUM(debit_amount-credit_amount) as balance')->value('balance'), 'Replacement counted once');
$draft = $service->reverse(1, 42, $posted->version, 'Complete the trial balance');
$input['clearing_account_id'] = null;
$draft = $service->save($input, 1, 42, $draft->version);
$before = JournalEntry::count();
rejects(fn () => $service->post(1, 42, $draft->version), ValidationException::class, 'Unbalanced setup without clearing');
check($before, JournalEntry::count(), 'Failed posting is atomic');
$input['lines'][] = ['account_id' => 5, 'partner_id' => null, 'side' => 'Cr', 'amount' => '30000.00'];
$draft = $service->save($input, 1, 42, $draft->version);
$posted = $service->post(1, 42, $draft->version);
check(4, JournalEntry::find($posted->journal_entry_id)->lines()->count(), 'Balanced trial balance needs no clearing');
$draft = $service->reverse(1, 42, $posted->version, 'Check historical import guard');
DB::table('mm_invoices')->insert(['plant_id' => 1, 'partner_id' => 1, 'invoice_type' => 'sales', 'invoice_date' => '2026-03-15']);
rejects(fn () => $service->post(1, 42, $draft->version), ValidationException::class, 'Unposted historical invoice blocks duplicate balance');

// Exercise real controller validation and permission checks without PHPUnit/Mockery.
$user = new class extends \App\Models\User {
    public bool $openingTestAdmin = true;
    public function isSystemAdmin(): bool { return $this->openingTestAdmin; }
    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection { return new \Illuminate\Database\Eloquent\Collection(); }
};
$user->id = 42;
auth()->setUser($user);
$controller = app(\App\Http\Controllers\OpeningBalanceController::class);
$csvPath = tempnam(sys_get_temp_dir(), 'opening-test-');
try {
    $beforeBatches = OpeningBalanceBatch::count();
    $beforeJournals = JournalEntry::count();
    file_put_contents($csvPath, "\xEF\xBB\xBFledger_code,patron_code,side,amount,reference\r\nL1,C1,Dr,100.25,\"Old, balance\"\r\nL3,,Dr,99.75,Bank\r\n");
    $request = \Illuminate\Http\Request::create('/finance/opening-balances/import', 'POST', ['cutover_date' => '2026-04-01'], [], [
        'file' => new \Illuminate\Http\UploadedFile($csvPath, 'opening.csv', 'text/csv', null, true),
    ]);
    $result = $controller->import($request)->getData(true);
    check(1, $result['lines'][0]['partner_id'], 'CSV patron code resolved');
    check('Old, balance', $result['lines'][0]['reference'], 'CSV quoted comma preserved');
    check(null, $result['lines'][1]['partner_id'], 'CSV blank patron is ledger row');
    check($beforeBatches, OpeningBalanceBatch::count(), 'CSV preview does not save draft');
    check($beforeJournals, JournalEntry::count(), 'CSV preview does not post');
    foreach (["bad,header\n", "ledger_code,patron_code,side,amount,reference\nOTHER,,Dr,100,\n", "ledger_code,patron_code,side,amount,reference\nL1,C1,Dr,100,\nL1,C1,Dr,100,\n"] as $badCsv) {
        file_put_contents($csvPath, $badCsv);
        rejects(fn () => $controller->import($request), ValidationException::class, 'CSV malformed / cross plant / duplicate');
    }
    session()->forget('active_plant_id');
    rejects(fn () => $controller->template(), HttpException::class, 'Missing plant rejected');
    session(['active_plant_id' => 1]);
    $user->openingTestAdmin = false;
    // With no permission granted, the real controller authorization must deny the import.
    rejects(fn () => $controller->import($request), HttpException::class, 'Unauthorized import rejected');
} finally {
    unlink($csvPath);
}

echo "Opening balance checks passed: $checks. SQLite in-memory database only.\n";
