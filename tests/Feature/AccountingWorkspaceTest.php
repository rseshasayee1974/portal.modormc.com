<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:21:19 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Accounting\LedgerResolver;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\AccountingMoney;
use App\Services\Finance\AccountingReports;
use App\Services\Finance\AccountingTally;
use App\Services\Finance\AccountingWorkspace;
use App\Services\Finance\SourceJournalService;
use App\Services\Gst\GstData;
use App\Services\Gst\GstImportPreview;
use App\Services\Gst\GstWorkspace;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

class AccountingWorkspaceTest extends TestCase
{
    private AccountingWorkspace $workspace;

    private ?string $mysqlDatabase = null;

    private ?\PDO $mysqlServer = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('ACCOUNTING_TEST_MYSQL') === '1') {
            $config = config('database.connections.mysql');
            $this->mysqlServer = new \PDO('mysql:host='.$config['host'].';port='.$config['port'], $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->mysqlDatabase = 'codex_accounting_test_'.bin2hex(random_bytes(8));
            $this->mysqlServer->exec('CREATE DATABASE `'.$this->mysqlDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            config(['database.connections.accounting_test_mysql' => array_merge($config, ['database' => $this->mysqlDatabase, 'engine' => 'InnoDB']), 'database.default' => 'accounting_test_mysql']);
        } else {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        }
        $this->travelTo(Carbon::parse('2026-10-18 12:00:00'));
        Schema::create('mm_plants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entity_id');
            $t->string('gstin');
            $t->softDeletes();
        });
        Schema::create('mm_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
        });
        Schema::create('mm_account_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('account_id');
            $t->string('title');
            $t->softDeletes();
        });
        Schema::create('mm_ledgers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('entity_id');
            $t->unsignedBigInteger('account_type_id');
            $t->string('title');
            $t->string('code');
            $t->boolean('is_pnl')->default(false);
            $t->tinyInteger('status')->default(1);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('mm_journal_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('entity_id');
            foreach (['voucher_type', 'voucher_number', 'ref_module', 'ref_name', 'narration', 'narration_label', 'is_status'] as $name) {
                $t->string($name, 255)->nullable();
            }
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->date('voucher_date');
            $t->date('posting_date');
            $t->decimal('total_debit', 19, 4);
            $t->decimal('total_credit', 19, 4);
            $t->unsignedBigInteger('reversal_of_id')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->boolean('is_deleted')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['plant_id', 'voucher_type', 'voucher_number']);
        });
        Schema::create('mm_journal_entry_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->unsignedBigInteger('account_id');
            $t->decimal('debit_amount', 19, 4);
            $t->decimal('credit_amount', 19, 4);
            $t->string('line_narration')->nullable();
            foreach (['narration_name', 'narration_label', 'partner_type'] as $name) {
                $t->string($name)->nullable();
            }
            $t->unsignedBigInteger('partner_id')->nullable();
            $t->unsignedBigInteger('tax_id')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->boolean('is_deleted')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        (require database_path('migrations/2026_10_17_141500_create_gst_compliance_tables.php'))->up();
        (require database_path('migrations/2026_10_18_211203_create_accounting_workspace.php'))->up();
        DB::table('mm_plants')->insert([['id' => 1, 'entity_id' => 10, 'gstin' => '27ABCDE1234F1Z5'], ['id' => 2, 'entity_id' => 10, 'gstin' => '27ABCDE1234F1Z5']]);
        DB::table('mm_accounts')->insert(['id' => 1, 'title' => 'ASSET']);
        DB::table('mm_account_types')->insert([['id' => 1, 'plant_id' => 1, 'account_id' => 1, 'title' => 'Current assets'], ['id' => 2, 'plant_id' => 2, 'account_id' => 1, 'title' => 'Sibling assets']]);
        $this->workspace = app(AccountingWorkspace::class);
        $this->account('1001', 'asset');
        $this->account('4001', 'income');
        $this->account('5001', 'expense');
        $this->account('3001', 'equity');
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->mysqlDatabase && preg_match('/^codex_accounting_test_[a-f0-9]{16}$/D', $this->mysqlDatabase)) {
                $this->mysqlServer->exec('DROP DATABASE `'.$this->mysqlDatabase.'`');
            }
        }
    }

    private function account(string $code, string $type, array $extra = []): object
    {
        return $this->workspace->account(1, 10, $extra + ['code' => $code, 'title' => 'Account '.$code, 'type' => $type, 'account_type_id' => 1, 'active' => true]);
    }

    private function draft(string $key = 'sale-1', string $date = '2026-10-10', string $amount = '100.0001'): object
    {
        return $this->workspace->journal(1, 10, $this->payload($key, $date, $amount));
    }

    private function payload(string $key = 'sale-1', string $date = '2026-10-10', string $amount = '100.0001'): array
    {
        return ['request_key' => $key, 'date' => $date, 'reference' => 'External reference', 'narration' => 'Test sale', 'lines' => [
            ['account_id' => 1, 'debit' => $amount, 'credit' => '0'], ['account_id' => 2, 'debit' => '0', 'credit' => $amount],
        ]];
    }

    private function assertHttpStatus(int $status, callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected HTTP '.$status);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode(), $e->getMessage());
        }
    }

    public function test_balanced_post_is_exact_idempotent_and_audited(): void
    {
        $j = $this->draft();
        $this->assertSame('DRAFT', $j->is_status);
        $posted = $this->workspace->post(1, 20, $j->id);
        $this->assertSame('POSTED', $posted->is_status);
        $this->assertSame(1000001, AccountingMoney::units((string) $posted->total_debit));
        $this->assertSame($j->id, $this->draft()->id);
        $this->workspace->post(1, 20, $j->id);
        $this->assertSame(1, DB::table('mm_journal_entries')->count());
        $this->assertTrue($this->workspace->audit(1)['valid']);
        $this->assertSame(1, DB::table('mm_accounting_events')->where('action', 'journal.posted')->count());
    }

    public function test_unbalanced_and_two_sided_lines_fail_without_partial_writes(): void
    {
        $v = $this->payload();
        $v['lines'][1]['credit'] = '99';
        $this->assertHttpStatus(422, fn () => $this->workspace->journal(1, 10, $v));
        $v['lines'][0]['credit'] = '1';
        $this->assertHttpStatus(422, fn () => $this->workspace->journal(1, 10, $v));
        $this->assertSame(0, DB::table('mm_journal_entries')->count());
    }

    public function test_posted_journal_cannot_be_edited_deleted_or_appended_through_sql(): void
    {
        $j = $this->draft();
        $this->workspace->post(1, 20, $j->id);
        $this->assertHttpStatus(409, fn () => $this->workspace->journal(1, 10, $this->payload(), $j->id));
        $this->assertHttpStatus(409, fn () => $this->workspace->deleteDraft(1, 10, $j->id));
        foreach ([fn () => DB::table('mm_journal_entries')->where('id', $j->id)->update(['narration' => 'Tampered']),
            fn () => DB::table('mm_journal_entry_lines')->where('journal_entry_id', $j->id)->delete(),
            fn () => DB::table('mm_accounting_journals')->where('journal_id', $j->id)->update(['sealed' => false]),
            fn () => DB::table('mm_accounting_events')->delete(),
            fn () => DB::table('mm_journal_entry_lines')->insert(['plant_id' => 1, 'journal_entry_id' => $j->id, 'account_id' => 1, 'debit_amount' => '1', 'credit_amount' => '0', 'created_by' => 10]),
        ] as $write) {
            try {
                $write();
                $this->fail('Expected immutable database trigger');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Immutable', $e->getMessage());
            }
        }
    }

    public function test_reversal_preserves_original_and_reconciles_balances_once(): void
    {
        $j = $this->draft();
        $this->workspace->post(1, 20, $j->id);
        $v = ['date' => '2026-10-15', 'reason' => 'Correction with verified source evidence', 'request_key' => 'reverse-1'];
        $r = $this->workspace->reverse(1, 20, $j->id, $v);
        $this->assertEquals($j->id, $r->reversal_of_id);
        $this->assertSame('POSTED', $this->workspace->entry(1, $j->id)->is_status);
        $this->assertSame($r->id, $this->workspace->reverse(1, 20, $j->id, $v)->id);
        $this->assertHttpStatus(409, fn () => $this->workspace->reverse(1, 20, $j->id, array_replace($v, ['request_key' => 'another'])));
        $s = app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18');
        $this->assertSame('0.0000', $s['totals']['closing']);
        $this->assertSame('0.0000', $s['totals']['trial_debit']);
        $this->assertTrue($this->workspace->audit(1)['valid']);
    }

    public function test_trial_ledger_profit_and_balance_sheet_respect_dates(): void
    {
        $j = $this->draft('opening', '2026-09-30', '50');
        $this->workspace->post(1, 20, $j->id);
        $j = $this->draft('current', '2026-10-10', '100');
        $this->workspace->post(1, 20, $j->id);
        $this->draft('draft-excluded', '2026-10-10', '700');
        $s = app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18');
        $this->assertSame('100.0000', $s['profit_loss']['income']);
        $this->assertSame('150.0000', $s['balance_sheet']['retained_result']);
        $this->assertSame('0.0000', $s['balance_sheet']['difference']);
        $this->assertSame('150.0000', $s['totals']['trial_debit']);
        $this->assertSame($s['totals']['trial_debit'], $s['totals']['trial_credit']);
        $ledger = app(AccountingReports::class)->ledger(1, 1, '2026-10-01', '2026-10-18');
        $this->assertSame('50.0000', $ledger['opening']);
        $this->assertSame('150.0000', $ledger['closing']);
        $this->assertCount(1, $ledger['rows']);
        $this->assertSame('150.0000', $ledger['rows'][0]['running_balance']);
        $early = app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-05');
        $this->assertSame('0.0000', $early['profit_loss']['income']);
    }

    public function test_duplicate_codes_are_rejected_but_allowed_in_sibling_plant(): void
    {
        $this->assertHttpStatus(422, fn () => $this->account('1001', 'asset'));
        $sibling = $this->workspace->account(2, 10, ['code' => '1001', 'title' => 'Sibling', 'type' => 'asset', 'account_type_id' => 2, 'active' => true]);
        $this->assertEquals(2, $sibling->plant_id);
        $this->assertHttpStatus(404, fn () => $this->workspace->account(1, 10, ['ledger_id' => $sibling->ledger_id, 'code' => '1002', 'title' => 'Wrong plant', 'type' => 'asset', 'account_type_id' => 1, 'active' => true]));
    }

    public function test_used_accounts_cannot_be_deactivated_and_parent_cycles_fail(): void
    {
        $j = $this->draft();
        $v = ['code' => '1001', 'title' => 'Account 1001', 'type' => 'asset', 'account_type_id' => 1, 'active' => false];
        $this->assertHttpStatus(409, fn () => $this->workspace->account(1, 10, $v, 1));
        $v['active'] = true;
        $v['parent_id'] = 1;
        $this->assertHttpStatus(422, fn () => $this->workspace->account(1, 10, $v, 1));
        $this->workspace->deleteDraft(1, 10, $j->id);
        $this->assertCount(0, app(AccountingReports::class)->journals(1, '2026-10-01', '2026-10-18', '', 1)['rows']);
    }

    public function test_cross_plant_journal_and_account_access_fails(): void
    {
        $j = $this->draft();
        $this->assertHttpStatus(404, fn () => $this->workspace->entry(2, $j->id));
        $this->assertHttpStatus(404, fn () => $this->workspace->post(2, 10, $j->id));
        $this->assertHttpStatus(404, fn () => app(AccountingReports::class)->ledger(2, 1, '2026-10-01', '2026-10-18'));
        $this->assertCount(0, app(AccountingReports::class)->summary(2, '2026-10-01', '2026-10-18')['accounts']);
    }

    public function test_closed_month_and_changed_idempotency_payload_are_rejected(): void
    {
        $j = $this->draft();
        $this->assertHttpStatus(409, fn () => $this->draft(amount: '200'));
        DB::table('mm_accounting_periods')->insert(['plant_id' => 1, 'period' => '2026-10', 'closed' => true]);
        $this->assertHttpStatus(409, fn () => $this->workspace->post(1, 20, $j->id));
        $this->assertSame('DRAFT', $this->workspace->entry(1, $j->id)->is_status);
    }

    public function test_posting_rolls_back_if_audit_fails_and_database_rejects_invalid_seal(): void
    {
        $j = $this->draft();
        DB::table('mm_journal_entry_lines')->where('id', $j->lines[0]->id)->update(['debit_amount' => '90']);
        $this->assertHttpStatus(422, fn () => $this->workspace->post(1, 20, $j->id));
        try {
            DB::table('mm_accounting_journals')->where('journal_id', $j->id)->update(['sealed' => true]);
            $this->fail('Seal must enforce balance');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Immutable', $e->getMessage());
        }
        DB::table('mm_journal_entry_lines')->where('id', $j->lines[0]->id)->update(['debit_amount' => '100.0001']);
        $sql = DB::getDriverName() === 'sqlite' ? "CREATE TRIGGER audit_failure BEFORE INSERT ON mm_accounting_events BEGIN SELECT RAISE(ABORT, 'Injected audit failure'); END" : "CREATE TRIGGER audit_failure BEFORE INSERT ON mm_accounting_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected audit failure'";
        DB::unprepared($sql);
        try {
            $this->workspace->post(1, 20, $j->id);
            $this->fail('Expected rollback');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Injected', $e->getMessage());
        }
        $this->assertSame('DRAFT', $this->workspace->entry(1, $j->id)->is_status);
        $this->assertFalse((bool) DB::table('mm_accounting_journals')->where('journal_id', $j->id)->value('sealed'));
    }

    public function test_tally_queue_confirms_only_verified_response_and_is_idempotent(): void
    {
        $j = $this->draft(amount: '100.00');
        $this->workspace->post(1, 20, $j->id);
        $tally = app(AccountingTally::class);
        $tally->configure(1, 10, ['tally_enabled' => true, 'tally_company' => 'Test & Company']);
        $job = $tally->enqueue(1, 10, $j->id);
        $this->assertSame($job->id, $tally->enqueue(1, 10, $j->id)->id);
        config(['gst_compliance.tally_url' => 'http://tally.test:9000']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<RESPONSE><CREATED>1</CREATED><ERRORS>0</ERRORS><LASTVCHID>42</LASTVCHID></RESPONSE>')]);
        $this->assertSame('confirmed', $tally->send(1, 10, $job->id)->status);
        $tally->send(1, 10, $job->id);
        Http::assertSentCount(1);
        $this->assertTrue($this->workspace->audit(1)['valid']);
        $this->assertHttpStatus(404, fn () => $tally->send(2, 10, $job->id));
    }

    public function test_tally_uncertain_delivery_requires_evidenced_resolution_before_retry(): void
    {
        $j = $this->draft(amount: '100.00');
        $this->workspace->post(1, 20, $j->id);
        $tally = app(AccountingTally::class);
        $tally->configure(1, 10, ['tally_enabled' => true, 'tally_company' => 'Test']);
        $job = $tally->enqueue(1, 10, $j->id);
        config(['gst_compliance.tally_url' => 'http://tally.test:9000']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<RESPONSE/>')]);
        $this->assertSame('uncertain', $tally->send(1, 10, $job->id)->status);
        $this->assertHttpStatus(409, fn () => $tally->send(1, 10, $job->id));
        Http::assertSentCount(1);
        $resolved = $tally->resolve(1, 20, $job->id, ['action' => 'retry_absent', 'reason' => 'Verified absent GUID in target export; all old workers stopped']);
        $this->assertSame('pending', $resolved->status);
        $this->assertTrue($this->workspace->audit(1)['valid']);
    }

    public function test_preview_reports_all_invalid_rows_and_duplicate_imports_without_writes(): void
    {
        $preview = app(GstImportPreview::class);
        $statement = ['schema' => 'modormc.2b.v1', 'gstin' => '27ABCDE1234F1Z5', 'period' => '2026-10', 'generated_at' => '2026-11-14', 'source_sha256' => str_repeat('a', 64), 'documents' => [['number' => 'broken1'], ['number' => 'broken2']]];
        $p = $preview->inspect(1, '2026-10', GstData::json($statement));
        $this->assertFalse($p['valid']);
        $this->assertArrayHasKey('documents.0.date', $p['errors']);
        $this->assertArrayHasKey('documents.1.date', $p['errors']);
        $this->assertSame(0, DB::table('mm_gst_imports')->count());
        $statement['documents'] = [];
        $raw = GstData::json($statement);
        $this->assertTrue($preview->inspect(1, '2026-10', $raw)['valid']);
        app(GstWorkspace::class)->import(1, '2026-10', 10, $raw);
        $this->assertTrue($preview->inspect(1, '2026-10', $raw)['duplicate']);
        $this->assertFalse($preview->inspect(2, '2026-10', $raw)['duplicate']);
    }

    private function authenticate(array $actions = ['VIEW']): void
    {
        Schema::create('mm_entity_users', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('entity_id');
            $t->unsignedBigInteger('plant_id')->nullable();
            $t->unsignedBigInteger('role_id')->nullable();
            $t->softDeletes();
        });
        DB::table('mm_entity_users')->insert(['user_id' => 10, 'entity_id' => 10, 'plant_id' => 1]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 10, 'email' => 'accountant@example.test']);
        $user->shouldReceive('isSystemAdmin')->andReturn(false);
        $user->shouldReceive('getDirectPermissions')->andReturn(collect($actions)->map(fn ($a) => (object) ['name' => 'ACCOUNTING.'.$a]));
        $this->withoutMiddleware();
        $this->actingAs($user);
        session(['active_plant_id' => 1, 'active_entity_id' => 10]);
    }

    public function test_http_post_reverse_and_sibling_access_require_explicit_grants(): void
    {
        $j = $this->draft();
        $this->authenticate();
        $this->postJson(route('accounting.journals.post', $j->id))->assertForbidden();
        $this->postJson(route('accounting.journals.reverse', $j->id), ['date' => '2026-10-15', 'reason' => 'Verified correction', 'request_key' => 'reverse-http'])->assertForbidden();
        $this->getJson(route('accounting.data', ['plant_id' => 2]))->assertStatus(409);
        session(['active_plant_id' => 2]);
        $this->getJson(route('accounting.data'))->assertForbidden();
    }

    public function test_http_report_payload_and_export_use_active_plant(): void
    {
        $j = $this->draft();
        $this->workspace->post(1, 20, $j->id);
        $this->authenticate(['VIEW', 'EXPORT']);
        $this->getJson(route('accounting.data', ['from' => '2026-10-01', 'to' => '2026-10-18']))->assertOk()->assertJsonPath('plant_id', 1)->assertJsonPath('summary.profit_loss.income', '100.0001');
        $csv = $this->get(route('accounting.export', ['report' => 'trial', 'from' => '2026-10-01', 'to' => '2026-10-18']));
        $csv->assertOk();
        $this->assertStringContainsString('100.0001', $csv->streamedContent());
    }

    public function test_missing_accounting_schema_shows_setup_and_blocks_reads_and_writes(): void
    {
        $this->authenticate(['VIEW', 'CREATE', 'EXPORT']);
        foreach (['mm_accounting_accounts', 'mm_accounting_periods', 'mm_accounting_journals', 'mm_accounting_events', 'mm_accounting_tally', 'mm_accounting_settings'] as $table) {
            Schema::rename($table, $table.'_pending');
            try {
                $this->get(route('accounting.index'))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page->component('Accounting/Setup')->where('plant_id', 1));
                $this->getJson(route('accounting.data'))->assertStatus(503)->assertJsonPath('message', 'Accounting setup is incomplete. Ask the administrator to run php artisan accounting:preflight, resolve the reported blockers, and apply the accounting workspace migration.');
                $this->postJson(route('accounting.journals.store'), $this->payload())->assertStatus(503);
                $this->getJson(route('accounting.export', ['report' => 'trial']))->assertStatus(503);
                $this->getJson(route('accounting.data', ['plant_id' => 2]))->assertStatus(409);
                $this->postJson(route('accounting.journals.post', 1))->assertForbidden();
            } finally {
                Schema::rename($table.'_pending', $table);
            }
        }
        $this->assertSame(0, DB::table('mm_journal_entries')->count());
        $this->getJson(route('accounting.data'))->assertOk()->assertJsonPath('plant_id', 1);
    }

    public function test_four_place_money_handles_large_values_without_binary_float(): void
    {
        $this->assertSame('999999999999.9999', AccountingMoney::decimal(AccountingMoney::units('999999999999.9999')));
        $this->assertSame('0.3000', AccountingMoney::decimal(AccountingMoney::units('0.1') + AccountingMoney::units('0.2')));
        $this->assertHttpStatus(422, fn () => AccountingMoney::units(0.1));
        $this->assertHttpStatus(422, fn () => AccountingMoney::units('1.00001'));
        $this->assertSame(12503, AccountingMoney::percentage(100025, '12.5'));
        $this->assertSame(1, AccountingMoney::percentage(1, '50'));
        $this->assertSame(-100, AccountingMoney::roundPaiseToRupee(-50));
    }

    public function test_source_document_reposting_reverses_and_replaces_without_overwriting(): void
    {
        $source = app(SourceJournalService::class);
        $header = ['voucher_type' => 'INVOICE', 'voucher_number' => 'INV/001', 'ref_module' => 'invoice', 'ref_id' => 99, 'voucher_date' => '2026-10-10', 'posting_date' => '2026-10-10', 'narration' => 'Source invoice'];
        $lines = [['account_id' => 1, 'debit_amount' => '100', 'credit_amount' => '0'], ['account_id' => 2, 'debit_amount' => '0', 'credit_amount' => '100']];
        $first = $source->post(1, 10, $header, $lines);
        $this->assertSame($first->id, $source->post(1, 10, $header, $lines)->id);
        $lines[0]['debit_amount'] = '125.01';
        $lines[1]['credit_amount'] = '125.01';
        $replacement = $source->post(1, 10, $header, $lines);
        $this->assertNotEquals($first->id, $replacement->id);
        $this->assertSame('100.0000', AccountingMoney::decimal(AccountingMoney::units((string) $this->workspace->entry(1, $first->id)->total_debit)));
        $this->assertSame(3, DB::table('mm_journal_entries')->count());
        $this->assertSame($replacement->id, $source->post(1, 10, $header, $lines)->id);
        $summary = app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18');
        $this->assertSame('125.0100', $summary['profit_loss']['income']);
        $this->assertSame('0.0000', $summary['totals']['closing']);
        $this->assertTrue($this->workspace->audit(1)['valid']);
    }

    public function test_concurrent_mysql_posting_has_unique_numbers_and_shared_request_is_idempotent(): void
    {
        if (! $this->mysqlDatabase) {
            $this->markTestSkipped('Run with ACCOUNTING_TEST_MYSQL=1 for real concurrent writers');
        }
        $connection = config('database.connections.accounting_test_mysql');
        $run = function (array $keys) use ($connection) {
            $processes = array_map(fn ($key) => new Process([PHP_BINARY, '-d', 'memory_limit=256M', base_path('tests/Support/accounting-concurrent-worker.php'), $key], base_path(), ['ACCOUNTING_WORKER_CONNECTION' => json_encode($connection), 'APP_ENV' => 'testing'], null, 60), $keys);
            foreach ($processes as $p) {
                $p->start();
            }
            $results = [];
            foreach ($processes as $p) {
                $p->wait();
                $this->assertTrue($p->isSuccessful(), $p->getErrorOutput());
                $results[] = json_decode($p->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        };
        $shared = $run(['parallel-shared', 'parallel-shared']);
        $this->assertSame($shared[0]['id'], $shared[1]['id']);
        $separate = $run(['parallel-one', 'parallel-two']);
        $this->assertNotSame($separate[0]['number'], $separate[1]['number']);
        $this->assertSame(3, DB::table('mm_journal_entries')->count());
        $this->assertTrue($this->workspace->audit(1)['valid']);
        $this->assertSame('0.0000', app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18')['totals']['closing']);
    }

    public function test_three_line_fractional_posting_and_sql_ownership_guards(): void
    {
        $v = $this->payload();
        $v['lines'] = [['account_id' => 1, 'debit' => '0.1', 'credit' => '0'], ['account_id' => 3, 'debit' => '0.2', 'credit' => '0'], ['account_id' => 2, 'debit' => '0', 'credit' => '0.3']];
        $j = $this->workspace->journal(1, 10, $v);
        $this->workspace->post(1, 20, $j->id);
        $this->assertSame('0.3000', AccountingMoney::decimal(AccountingMoney::units((string) $j->total_debit)));
        $this->assertSame('0.0000', app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18')['totals']['closing']);
    }

    public function test_invalid_source_replacement_rolls_back_its_reversal_and_audit(): void
    {
        $source = app(SourceJournalService::class);
        $header = ['voucher_type' => 'INVOICE', 'voucher_number' => 'INV/ROLLBACK', 'ref_module' => 'invoice', 'ref_id' => 100, 'voucher_date' => '2026-10-10', 'posting_date' => '2026-10-10', 'narration' => 'Rollback test'];
        $lines = [['account_id' => 1, 'debit_amount' => '100', 'credit_amount' => '0'], ['account_id' => 2, 'debit_amount' => '0', 'credit_amount' => '100']];
        $j = $source->post(1, 10, $header, $lines);
        $checkpoint = $this->workspace->audit(1);
        $lines[0]['debit_amount'] = '101';
        $this->assertHttpStatus(422, fn () => $source->post(1, 10, $header, $lines));
        $this->assertSame(1, DB::table('mm_journal_entries')->count());
        $this->assertNull($this->workspace->entry(1, $j->id)->reversed_by);
        $this->assertSame($checkpoint, $this->workspace->audit(1));
    }

    public function test_migration_refuses_to_roll_back_used_financial_history(): void
    {
        $j = $this->draft();
        $this->workspace->post(1, 20, $j->id);
        $migration = require database_path('migrations/2026_10_18_211203_create_accounting_workspace.php');
        try {
            $migration->down();
            $this->fail('Used accounting evidence must survive application rollback');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Accounting history exists', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('mm_accounting_accounts'));
        $this->assertSame(4, DB::table('mm_ledgers')->count());
        $this->assertSame(1, DB::table('mm_journal_entries')->count());
        $this->assertTrue(Schema::hasTable('mm_accounting_journals'));
    }

    public function test_http_create_post_edit_denial_and_reversal_workflow(): void
    {
        $this->authenticate(['VIEW', 'CREATE', 'UPDATE', 'POST', 'REVERSE']);
        $j = $this->postJson(route('accounting.journals.store', ['plant_id' => 1]), $this->payload())->assertCreated()->json();
        $this->postJson(route('accounting.journals.post', $j['id']))->assertOk()->assertJsonPath('is_status', 'POSTED');
        $this->putJson(route('accounting.journals.update', $j['id']), $this->payload())->assertStatus(409);
        $this->deleteJson(route('accounting.journals.delete', $j['id']))->assertStatus(409);
        $this->postJson(route('accounting.journals.reverse', $j['id']), ['date' => '2026-10-18', 'reason' => 'Verified correction with supporting reference', 'request_key' => 'http-reversal'])->assertOk()->assertJsonPath('reversal_of_id', $j['id']);
        $this->assertSame('0.0000', app(AccountingReports::class)->summary(1, '2026-10-01', '2026-10-18')['totals']['closing']);
    }

    public function test_gst_and_accounting_audit_records_are_append_only_on_real_driver(): void
    {
        $r = app(GstWorkspace::class)->rule(1, '2026-10', 10, ['version' => 'driver-test', 'reason' => 'Verify effective policy against source documents']);
        try {
            DB::table('mm_gst_rules')->where('id', $r->id)->update(['payload' => '{}']);
            $this->fail('Expected GST evidence trigger');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Append-only', $e->getMessage());
        }
        $this->assertTrue($this->workspace->audit(1)['valid']);
    }

    public function test_listener_reachability_does_not_claim_company_or_sync_success(): void
    {
        config(['gst_compliance.tally_url' => 'http://tally.test:9000']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<RESPONSE>Running</RESPONSE>')]);
        $r = app(AccountingTally::class)->connection(1, 10);
        $this->assertSame('listener_reachable', $r['status']);
        $this->assertFalse($r['company_verified']);
        $this->assertSame(0, DB::table('mm_accounting_tally')->where('status', 'confirmed')->count());
    }

    public function test_percentage_invoice_discount_posts_its_value_instead_of_the_rate(): void
    {
        $resolver = \Mockery::mock(LedgerResolver::class);
        $resolver->shouldReceive('resolve')->once()->with(1, 'Invoice', 'discount_account', 'Discount')->andReturn(3);
        $this->app->instance(LedgerResolver::class, $resolver);
        $invoice = new Invoice(['plant_id' => 1, 'invoice_type' => 'invoice', 'subtotal' => '1000.25', 'global_discount_type' => '%', 'global_discount' => '12.5']);
        $discount = $invoice->getAdjustmentLines()->sole();
        $this->assertSame(-12503, $discount->amountCents);
        $this->assertSame(3, $discount->accountId);
    }
}
