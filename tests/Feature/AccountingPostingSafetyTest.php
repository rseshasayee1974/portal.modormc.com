<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:33:55 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Accounting\AccountingPostingService;
use App\Accounting\LedgerResolver;
use App\Contracts\Postable;
use App\DTO\AdjustmentLineDTO;
use App\DTO\TaxLineDTO;
use App\Exceptions\AccountingException;
use App\Models\JournalEntry;
use App\Services\Gst\GstWorkspace;
use App\Services\Gst\TallyJournalExport;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountingPostingSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('mm_plants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entity_id')->default(10);
            $t->string('gstin')->default('27ABCDE1234F1Z5');
            $t->softDeletes();
        });
        Schema::create('mm_ledgers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->string('title')->default('Ledger & Co');
            $t->softDeletes();
        });
        Schema::create('mm_journal_entries', function (Blueprint $t) {
            $t->id();
            foreach (['entity_id', 'plant_id', 'ref_id', 'created_by'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['voucher_type', 'voucher_number', 'ref_module', 'voucher_date', 'posting_date', 'narration', 'narration_label', 'is_status'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('total_debit', 19, 4);
            $t->decimal('total_credit', 19, 4);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['plant_id', 'voucher_type', 'voucher_number']);
        });
        Schema::create('mm_journal_entry_lines', function (Blueprint $t) {
            $t->id();
            foreach (['journal_entry_id', 'plant_id', 'account_id', 'partner_id', 'tax_id', 'created_by'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            foreach (['partner_type', 'narration_name', 'narration_label', 'line_narration'] as $c) {
                $t->string($c)->nullable();
            }
            $t->decimal('debit_amount', 19, 4);
            $t->decimal('credit_amount', 19, 4);
            $t->timestamps();
            $t->softDeletes();
        });
        DB::table('mm_plants')->insert(['id' => 1]);
        foreach ([1, 2, 3] as $id) {
            DB::table('mm_ledgers')->insert(['id' => $id, 'plant_id' => 1]);
        }
        DB::table('mm_ledgers')->insert(['id' => 4, 'plant_id' => 2]);
    }

    private function document(int $base = 2, int $id = 99, bool $rounding = false): Postable
    {
        $d = \Mockery::mock(Postable::class);
        foreach (['getDocumentId' => $id, 'getDocumentType' => 'invoice', 'getVoucherNumber' => 'INV/1', 'getVoucherDate' => Carbon::parse('2026-10-17'),
            'getPlantId' => 1, 'getEntityId' => 10, 'getPartnerId' => null, 'getPartnerLedgerId' => 1, 'getPartnerName' => 'Customer', 'getBaseAccountId' => $base, 'getContraAccountId' => null,
            'getSubtotalCents' => 10000, 'getTaxTotalCents' => 1800, 'getTotalAmountCents' => 11800, 'getTaxLines' => collect([new TaxLineDTO(1800, 3, 'IGST', null)]),
            'getAdjustmentLines' => $rounding ? collect([new AdjustmentLineDTO(10, 2, 'Round Off')]) : collect()] as $method => $value) {
            $d->shouldReceive($method)->andReturn($value);
        }

        return $d;
    }

    private function postDocument(Postable $d): JournalEntry
    {
        return JournalEntry::withoutEvents(fn () => app(AccountingPostingService::class)->post($d));
    }

    public function test_standalone_post_is_atomic_balanced_and_replay_does_not_duplicate(): void
    {
        $first = $this->postDocument($this->document());
        $retry = $this->postDocument($this->document());
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame(3, $retry->lines()->count());
        $this->assertSame('118.0000', $retry->total_debit);
        $this->assertSame($retry->total_debit, $retry->total_credit);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_failure_during_line_insert_rolls_back_header_and_prior_line(): void
    {
        DB::unprepared("CREATE TRIGGER test_fail_line BEFORE INSERT ON mm_journal_entry_lines WHEN NEW.account_id = 2 BEGIN SELECT RAISE(ABORT, 'simulated persistence failure'); END");
        try {
            $this->postDocument($this->document());
            $this->fail();
        } catch (QueryException $e) {
            $this->assertStringContainsString('simulated persistence failure', $e->getMessage());
        }
        $this->assertSame(0, DB::table('mm_journal_entries')->count());
        $this->assertSame(0, DB::table('mm_journal_entry_lines')->count());
    }

    public function test_cross_plant_override_ledger_is_rejected_before_any_posting(): void
    {
        try {
            $this->postDocument($this->document(4));
            $this->fail();
        } catch (AccountingException $e) {
            $this->assertStringContainsString('document plant', $e->getMessage());
        }
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_same_voucher_number_cannot_overwrite_another_document(): void
    {
        $j = $this->postDocument($this->document());
        try {
            $this->postDocument($this->document(2, 100));
            $this->fail();
        } catch (AccountingException $e) {
            $this->assertStringContainsString('another source', $e->getMessage());
        }
        $this->assertSame(99, (int) $j->fresh()->ref_id);
        $this->assertSame(3, $j->lines()->count());
    }

    public function test_rounding_offsets_do_not_persist_two_sided_or_zero_lines(): void
    {
        $resolver = \Mockery::mock(LedgerResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(2);
        $this->app->instance(LedgerResolver::class, $resolver);
        $j = $this->postDocument($this->document(2, 99, true));
        foreach ($j->lines as $line) {
            $this->assertTrue(((float) $line->debit_amount > 0) xor ((float) $line->credit_amount > 0));
        }
        $this->assertSame(3, $j->lines()->count());
        $this->assertSame($j->total_debit, $j->total_credit);
    }

    public function test_tally_export_escapes_xml_uses_stable_guids_and_checks_source_balance(): void
    {
        $j = $this->postDocument($this->document());
        $export = app(TallyJournalExport::class);
        $xml = $export->build(1, '2026-10', 'Demo & Sons');
        $this->assertStringContainsString('Demo &amp; Sons', $xml);
        $this->assertStringContainsString('Ledger &amp; Co', $xml);
        $this->assertStringContainsString('<AMOUNT>-118.00</AMOUNT>', $xml);
        $this->assertSame($xml, $export->build(1, '2026-10', 'Demo & Sons'));
        DB::table('mm_journal_entries')->where('id', $j->id)->update(['total_debit' => 119]);
        $this->expectException(HttpException::class);
        $export->build(1, '2026-10', 'Demo & Sons');
    }

    private function inboundSetup(): array
    {
        (require database_path('migrations/2026_10_17_141500_create_gst_compliance_tables.php'))->up();

        return ['remote_guid' => 'external-tally-guid', 'date' => '2026-10-17', 'narration' => 'Mapped Tally journal', 'reason' => 'Verified source Tally export and approved ledger mapping',
            'lines' => [['account_id' => 1, 'debit' => 11800, 'credit' => 0], ['account_id' => 2, 'debit' => 0, 'credit' => 11800]]];
    }

    public function test_tally_inbound_requires_independent_review_is_idempotent_and_never_echoes(): void
    {
        $payload = $this->inboundSetup();
        $workspace = app(GstWorkspace::class);
        $record = $workspace->stageInbound(1, '2026-10', 10, $payload);
        $retry = $workspace->stageInbound(1, '2026-10', 10, $payload);
        $this->assertSame($record->id, $retry->id);
        $this->assertSame(0, JournalEntry::count());
        try {
            $workspace->review(1, '2026-10', 10, 'inbound', $record->id, ['decision' => 'include', 'reason' => 'Own approval should be denied']);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        JournalEntry::withoutEvents(fn () => $workspace->review(1, '2026-10', 20, 'inbound', $record->id, ['decision' => 'include', 'reason' => 'Independent mapping and amounts verified']));
        $this->assertSame(1, JournalEntry::count());
        $this->assertSame('tally_import', JournalEntry::first()->ref_module);
        $this->assertSame(2, JournalEntry::first()->lines()->count());
        $this->assertTrue($workspace->verify(1, '2026-10')['valid']);
        try {
            $workspace->review(1, '2026-10', 20, 'inbound', $record->id, ['decision' => 'include', 'reason' => 'Repeated approval must not duplicate posting']);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->expectException(HttpException::class);
        app(TallyJournalExport::class)->build(1, '2026-10', 'Demo');
    }

    public function test_tally_inbound_changed_guid_payload_and_outbound_echo_are_rejected(): void
    {
        $payload = $this->inboundSetup();
        $workspace = app(GstWorkspace::class);
        $old = $workspace->stageInbound(1, '2026-10', 10, $payload);
        $payload['narration'] = 'Changed remote voucher';
        $current = $workspace->stageInbound(1, '2026-10', 10, $payload);
        $this->assertNotSame($old->id, $current->id);
        try {
            $workspace->review(1, '2026-10', 20, 'inbound', $old->id, ['decision' => 'include', 'reason' => 'Superseded mapping must be refused']);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        JournalEntry::withoutEvents(fn () => $workspace->review(1, '2026-10', 20, 'inbound', $current->id, ['decision' => 'include', 'reason' => 'Verified corrected mapping independently']));
        $payload['narration'] = 'Changed after posting';
        try {
            $workspace->stageInbound(1, '2026-10', 10, $payload);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $j = $this->postDocument($this->document());
        $payload['remote_guid'] = app(TallyJournalExport::class)->guid(1, $j->id);
        $this->expectException(HttpException::class);
        $workspace->stageInbound(1, '2026-10', 10, $payload);
    }

    public function test_tally_inbound_invalid_mapping_rolls_back_review_and_posting(): void
    {
        $payload = $this->inboundSetup();
        $payload['lines'][1]['account_id'] = 4;
        $workspace = app(GstWorkspace::class);
        $record = $workspace->stageInbound(1, '2026-10', 10, $payload);
        try {
            JournalEntry::withoutEvents(fn () => $workspace->review(1, '2026-10', 20, 'inbound', $record->id, ['decision' => 'include', 'reason' => 'Cross plant mapping must fail atomically']));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, JournalEntry::count());
        $this->assertSame(0, DB::table('mm_gst_reviews')->count());
    }

    public function test_tally_response_requires_a_created_voucher_and_rejects_unsafe_xml(): void
    {
        $export = app(TallyJournalExport::class);
        $this->assertSame('confirmed', $export->outcome('<RESPONSE><CREATED>1</CREATED><ERRORS>0</ERRORS><LASTVCHID>42</LASTVCHID></RESPONSE>'));
        $this->assertSame('uncertain',$export->outcome('<RESPONSE><CREATED>0</CREATED><ERRORS>0</ERRORS></RESPONSE>'));
        $this->assertSame('failed',$export->outcome('<RESPONSE><CREATED>0</CREATED><ERRORS>1</ERRORS></RESPONSE>'));
        $this->assertSame('uncertain',$export->outcome('<!DOCTYPE r [<!ENTITY x SYSTEM "file:///secret">]><RESPONSE>&x;</RESPONSE>'));
    }
}
