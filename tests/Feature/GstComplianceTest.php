<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:30:42 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\User;
use App\Services\Gst\GstBooks;
use App\Services\Gst\GstData;
use App\Services\Gst\GstMatcher;
use App\Services\Gst\GstWorkspace;
use App\Services\PlantContextService;
use App\Services\Reports\Gstr3bReportService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GstComplianceTest extends TestCase
{
    private GstWorkspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('mm_plants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('entity_id');
            $t->string('gstin');
            $t->softDeletes();
        });
        Schema::create('mm_patrons', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->string('gstin');
            $t->string('legal_name');
            $t->softDeletes();
        });
        Schema::create('mm_invoices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('partner_id');
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('invoice_type');
            $t->string('invoice_label')->nullable();
            $t->string('prefix')->nullable();
            $t->string('invoice_number');
            $t->date('invoice_date');
            $t->string('status');
            $t->decimal('subtotal', 19, 2);
            $t->decimal('tax_amount', 19, 2);
            $t->softDeletes();
        });
        Schema::create('mm_order_taxes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->unsignedBigInteger('order_id');
            $t->string('order_type');
            $t->string('name');
            $t->decimal('amount', 19, 2);
            $t->softDeletes();
        });
        (require database_path('migrations/2026_10_17_141500_create_gst_compliance_tables.php'))->up();
        DB::table('mm_plants')->insert([['id' => 1, 'entity_id' => 10, 'gstin' => '27ABCDE1234F1Z5'], ['id' => 2, 'entity_id' => 20, 'gstin' => '29ABCDE1234F1Z5']]);
        DB::table('mm_patrons')->insert(['id' => 1, 'plant_id' => 1, 'gstin' => '29AAAAA0000A1Z5', 'legal_name' => 'Supplier']);
        $this->workspace = app(GstWorkspace::class);
    }

    private function book(string $type = 'Bill', string $status = 'Approved', int $plant = 1, string $date = '2026-10-01'): int
    {
        $id = DB::table('mm_invoices')->insertGetId(['plant_id' => $plant, 'partner_id' => 1, 'invoice_type' => $type, 'invoice_number' => 'INV/001', 'invoice_date' => $date, 'status' => $status, 'subtotal' => '100.00', 'tax_amount' => '18.00']);
        DB::table('mm_order_taxes')->insert(['plant_id' => $plant, 'order_id' => $id, 'order_type' => in_array($type, ['Bill', 'purchase']) ? 'Purchase' : 'Invoice', 'name' => 'CGST', 'amount' => '9.00']);
        DB::table('mm_order_taxes')->insert(['plant_id' => $plant, 'order_id' => $id, 'order_type' => in_array($type, ['Bill', 'purchase']) ? 'Purchase' : 'Invoice', 'name' => 'SGST', 'amount' => '9.00']);

        return $id;
    }

    private function statement(): array
    {
        return ['schema' => 'modormc.2b.v1', 'gstin' => '27ABCDE1234F1Z5', 'period' => '2026-10', 'source_sha256' => str_repeat('a', 64), 'generated_at' => '2026-11-14',
            'documents' => [['supplier_gstin' => '29AAAAA0000A1Z5', 'number' => 'INV/001', 'date' => '2026-10-01', 'kind' => 'INV', 'amounts' => ['taxable' => 10000, 'igst' => 0, 'cgst' => 900, 'sgst' => 900, 'cess' => 0],
                'itc_available' => true, 'ims' => 'accepted', 'reverse_charge' => false, 'section' => 'B2B']]];
    }

    private function rule(): object
    {
        $r = $this->workspace->rule(1, '2026-10', 10, ['version' => 'test-v1', 'reason' => 'Verified relevant official sources']);
        $this->workspace->review(1, '2026-10', 20, 'rule', $r->id, ['decision' => 'include', 'reason' => 'Independently verified effective rules']);

        return $r;
    }

    private function classification(int $actor = 10, ?array $allocations = null): object
    {
        $book = $this->workspace->view(1, '2026-10')['books'][0];

        return $this->workspace->classify(1, '2026-10', $actor, ['source_key' => $book['source_key'], 'source_hash' => $book['hash'], 'reason' => 'Invoice, receipt and GST treatment verified',
            'supplier_document'=>['number'=>'INV/001','date'=>'2026-10-01','supplier_gstin'=>'29AAAAA0000A1Z5','kind'=>'INV','reverse_charge'=>false],
            'allocations' => $allocations ?? [['row' => '4A5', 'amounts' => ['cgst' => 900, 'sgst' => 900]]]]);
    }

    private function prepared(): object
    {
        $this->book();
        $this->rule();
        $f = $this->classification();
        $this->workspace->review(1, '2026-10', 20, 'classification', $f->id, ['decision' => 'include', 'reason' => 'Verified source amounts and legal eligibility']);

        return $this->workspace->prepare(1, '2026-10', 10);
    }

    public function test_source_uses_actual_tax_heads_and_first_day_not_supplier_state(): void
    {
        $this->book();
        $this->book('Bill', 'Draft');
        $this->book('Bill', 'Approved', 2);
        $this->book('Bill', 'Approved', 1, '2026-09-30');
        $rows = app(GstBooks::class)->period(1, '2026-10');
        $this->assertCount(1, $rows);
        $this->assertSame(900, $rows[0]['amounts']['cgst']);
        $this->assertSame(0, $rows[0]['amounts']['igst']);
        $this->assertSame([], $rows[0]['issues']);
    }

    public function test_legacy_report_uses_bills_and_marks_input_tax_provisional(): void
    {
        $this->book();
        $this->book('Bill', 'Draft');
        $context = \Mockery::mock(PlantContextService::class);
        $context->shouldReceive('requirePlantId')->andReturn(1);
        $report = (new Gstr3bReportService($context))->generate(['start' => '2026-10-01', 'end' => '2026-10-31']);
        $this->assertTrue($report['is_provisional']);
        $this->assertEquals(9, $report['table4']['other_itc']['cgst']);
        $this->assertEquals(0, $report['table4']['other_itc']['igst']);
    }

    public function test_import_is_idempotent_retains_revisions_and_matches_actual_bills(): void
    {
        $this->book();
        $raw = GstData::json($this->statement());
        $a = $this->workspace->import(1, '2026-10', 10, $raw);
        $b = $this->workspace->import(1, '2026-10', 10, $raw);
        $this->assertSame($a->id, $b->id);
        $this->assertContains('supplier_reference_required',array_column($this->workspace->view(1,'2026-10')['matches'],'status'));
        $this->classification();
        $this->assertSame('exact', $this->workspace->view(1, '2026-10')['matches'][0]['status']);
        $input = $this->statement();
        $input['documents'][0]['ims'] = 'pending';
        $this->workspace->import(1, '2026-10', 10, GstData::json($input));
        $view = $this->workspace->view(1, '2026-10');
        $this->assertCount(2, $view['imports']);
        $this->assertSame('itc_review_required', $view['matches'][0]['status']);
        $this->assertTrue($this->workspace->verify(1, '2026-10')['valid']);
    }

    public function test_matching_uses_supplier_reference_not_internal_bill_number(): void
    {
        $id=$this->book();DB::table('mm_invoices')->where('id',$id)->update(['prefix'=>'BILL','invoice_number'=>'00001']);
        $this->workspace->import(1,'2026-10',10,GstData::json($this->statement()));
        $this->classification();$view=$this->workspace->view(1,'2026-10');
        $this->assertSame('BILL/00001',$view['books'][0]['number']);$this->assertSame('INV/001',$view['matches'][0]['number']);$this->assertSame('exact',$view['matches'][0]['status']);
    }

    public function test_wrong_recipient_import_rolls_back_everything(): void
    {
        try {
            $this->workspace->import(2, '2026-10', 10, GstData::json($this->statement()));
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('mm_gst_imports')->count());
        $this->assertSame(0, DB::table('mm_gst_events')->count());
    }

    public function test_matching_never_chooses_a_duplicate_and_compares_each_head(): void
    {
        $a = $this->statement()['documents'][0] + ['source_key' => 'invoice:1'];
        $matcher = new GstMatcher;
        $this->assertSame('ambiguous', $matcher->match([$a, $a], [$a])[0]['status']);
        $this->assertSame('ambiguous', $matcher->match([$a], [$a, $a])[0]['status']);
        $b = $a;
        $b['amounts']['cgst'] = 800;
        $b['amounts']['sgst'] = 1000;
        $this->assertSame('tolerance', $matcher->match([$a], [$b])[0]['status']);
        $b['amounts']['sgst']++;
        $this->assertSame('amount_mismatch', $matcher->match([$a], [$b])[0]['status']);
        $b = $a;
        $b['number'] = 'INV001';
        $this->assertSame('number_variant', $matcher->match([$a], [$b])[0]['status']);
        $b = $a;
        $b['date'] = '2025-10-01';
        $this->assertSame('missing_in_2b', $matcher->match([$a], [$b])[0]['status']);
    }

    public function test_unclassified_sources_and_unapproved_rules_block_approval(): void
    {
        $this->book();
        $s = $this->workspace->prepare(1, '2026-10', 10);
        $payload = json_decode($s->payload, true);
        $this->assertCount(2, $payload['blockers']);
        $this->expectException(HttpException::class);
        $this->workspace->review(1, '2026-10', 20, 'snapshot', $s->id, ['decision' => 'include', 'reason' => 'Cannot approve without classified evidence', 'checklist' => array_fill_keys(array_keys(GstData::CHECKS), true)]);
    }

    public function test_net_itc_separates_disclosures_reversals_and_cess(): void
    {
        $this->book();
        $this->rule();
        $f = $this->classification(10, [['row' => '4A5', 'amounts' => ['igst' => 10000, 'cess' => 500]], ['row' => '4B1', 'amounts' => ['igst' => 1000]], ['row' => '4B2', 'amounts' => ['igst' => 2000]], ['row' => '4D1', 'amounts' => ['igst' => 500]], ['row' => '4D2', 'amounts' => ['igst' => 700]]]);
        $this->workspace->review(1, '2026-10', 20, 'classification', $f->id, ['decision' => 'include', 'reason' => 'All gross, reversal and disclosure amounts verified']);
        $s = $this->workspace->prepare(1, '2026-10', 10);
        $p = json_decode($s->payload, true);
        $this->assertSame(7000, $p['rows']['4C']['igst']);
        $this->assertSame(500, $p['rows']['4C']['cess']);
        $this->assertSame(500, $p['rows']['4D1']['igst']);
        $this->assertSame([], $p['blockers']);
    }

    public function test_source_edits_make_snapshots_stale_even_when_module_revision_unchanged(): void
    {
        $s = $this->prepared();
        DB::table('mm_invoices')->where('id', 1)->update(['subtotal' => '101.00']);
        $this->assertTrue($this->workspace->view(1, '2026-10')['snapshots'][0]->stale);
        try {
            $this->workspace->review(1, '2026-10', 20, 'snapshot', $s->id, ['decision' => 'include', 'reason' => 'Stale data cannot pass independent approval', 'checklist' => array_fill_keys(array_keys(GstData::CHECKS), true)]);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $next = $this->workspace->prepare(1, '2026-10', 10);
        $this->assertNotEmpty(json_decode($next->payload, true)['blockers']);
    }

    public function test_source_deletion_is_visible_and_cannot_silently_disappear_from_review(): void
    {
        $this->prepared();
        DB::table('mm_invoices')->where('id', 1)->update(['deleted_at' => now()]);
        $s = $this->workspace->prepare(1, '2026-10', 10);
        $this->assertStringContainsString('source removed', implode(' ', json_decode($s->payload, true)['blockers']));
        $fact = DB::table('mm_gst_classifications')->first();
        $this->workspace->review(1, '2026-10', 20, 'classification', $fact->id, ['decision' => 'exclude', 'reason' => 'Source cancelled: checked reversal evidence and excluded superseded treatment']);
        $s = $this->workspace->prepare(1, '2026-10', 10);
        $this->assertSame([], json_decode($s->payload, true)['blockers']);
    }

    public function test_direct_ledger_edit_invalidates_worksheet_without_invoice_edits(): void
    {
        Schema::create('mm_journal_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plant_id');
            $t->date('voucher_date');
            $t->string('narration');
        });
        Schema::create('mm_journal_entry_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('journal_entry_id');
            $t->integer('debit_amount');
        });
        DB::table('mm_journal_entries')->insert(['id' => 1, 'plant_id' => 1, 'voucher_date' => '2026-10-10', 'narration' => 'Original']);
        DB::table('mm_journal_entry_lines')->insert(['journal_entry_id' => 1, 'debit_amount' => 100]);
        $this->prepared();
        DB::table('mm_journal_entry_lines')->update(['debit_amount' => 200]);
        $this->assertTrue($this->workspace->view(1, '2026-10')['snapshots'][0]->stale);
    }

    public function test_maker_cannot_review_own_classification(): void
    {
        $this->book();
        $f = $this->classification();
        $this->expectException(HttpException::class);
        $this->workspace->review(1, '2026-10', 10, 'classification', $f->id, ['decision' => 'include', 'reason' => 'Self approval must fail']);
    }

    public function test_cross_plant_review_and_export_are_scoped(): void
    {
        $s = $this->prepared();
        try {
            $this->workspace->review(2, '2026-10', 20, 'snapshot', $s->id, ['decision' => 'include', 'reason' => 'Cross plant access must fail']);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->expectException(HttpException::class);
        $this->workspace->snapshot(2, '2026-10', $s->id);
    }

    public function test_full_checklist_and_independent_approval_are_retained_without_staling_snapshot(): void
    {
        $s = $this->prepared();
        try {
            $this->workspace->review(1, '2026-10', 20, 'snapshot', $s->id, ['decision' => 'include', 'reason' => 'Incomplete checklist must fail']);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->workspace->review(1, '2026-10', 20, 'snapshot', $s->id, ['decision' => 'include', 'reason' => 'All portal evidence, balances and rules verified', 'checklist' => array_fill_keys(array_keys(GstData::CHECKS), true)]);
        $v = $this->workspace->view(1, '2026-10');
        $this->assertFalse($v['snapshots'][0]->stale);
        $this->assertNotNull($v['snapshots'][0]->approval);
        $this->assertTrue($this->workspace->verify(1, '2026-10')['valid']);
    }

    public function test_new_import_invalidates_approved_worksheet(): void
    {
        $s = $this->prepared();
        $this->workspace->import(1, '2026-10', 10, GstData::json($this->statement()));
        $this->assertTrue($this->workspace->view(1, '2026-10')['snapshots'][0]->stale);
    }

    public function test_adjustments_and_prior_liability_breakup_and_advance_exclusion(): void
    {
        $this->rule();
        $f = $this->workspace->classify(1, '2026-10', 10, ['source_key' => 'adjustment:prior-liability', 'reason' => 'Evidence for previously omitted supply liability',
            'allocations' => [['row' => '3.1.a', 'amounts' => ['taxable' => 10000, 'igst' => 1800], 'liability_period' => '2026-09', 'pos' => '29', 'recipient' => 'unregistered'], ['row' => '3.1.a', 'amounts' => ['taxable' => 5000, 'igst' => 900], 'advance' => true, 'pos' => '29', 'recipient' => 'unregistered']]]);
        $this->workspace->review(1, '2026-10', 20, 'classification', $f->id, ['decision' => 'include', 'reason' => 'Verified prior supply and advance evidence']);
        $s = $this->workspace->prepare(1, '2026-10', 10);
        $p = json_decode($s->payload, true);
        $this->assertSame(1800, $p['liability_breakup']['2026-09']['igst']);
        $this->assertSame(10000, $p['table32'][0]['taxable']);
    }

    public function test_database_enforces_immutable_evidence(): void
    {
        $r = $this->rule();
        $this->expectException(QueryException::class);
        DB::table('mm_gst_rules')->where('id', $r->id)->update(['payload' => '{}']);
    }

    public function test_money_conversion_is_decimal_safe_and_rounds_four_place_source(): void
    {
        $this->assertSame(10001, GstData::paise('100.0050'));
        $this->assertSame(-101, GstData::paise('-1.0050'));
        $this->assertSame(1, GstData::paise('0.01'));
    }

    public function test_http_permissions_cannot_be_bypassed_by_body_actor_or_plant(): void
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
        $this->withoutMiddleware();
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 10, 'email' => 'maker@example.test']);
        $user->shouldReceive('isSystemAdmin')->andReturn(false);
        $user->shouldReceive('getDirectPermissions')->andReturn(collect());
        $this->actingAs($user);
        session(['active_plant_id' => 1, 'active_entity_id' => 10]);
        $this->postJson(route('gst-compliance.snapshots'),['period' => '2026-10', 'plant_id' => 2, 'created_by' => 20])->assertForbidden();
        $this->assertSame(0,DB::table('mm_gst_snapshots')->count());
    }

    private function authenticatePlantUser(): void
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
        $user->forceFill(['id' => 10, 'email' => 'plant-user@example.test']);
        $user->shouldReceive('isSystemAdmin')->andReturn(false);
        $user->shouldReceive('getDirectPermissions')->andReturn(collect([
            (object) ['name' => 'GST_COMPLIANCE.VIEW'],
            (object) ['name' => 'GST_COMPLIANCE.PREPARE'],
        ]));
        $this->withoutMiddleware();
        $this->actingAs($user);
        session(['active_plant_id' => 1, 'active_entity_id' => 10]);
    }

    public function test_http_plant_context_assertion_rejects_stale_reads_and_writes(): void
    {
        $this->authenticatePlantUser();
        $context = ['period' => '2026-10', 'plant_id' => 2];
        $this->getJson(route('gst-compliance.data', $context))->assertStatus(409);
        $this->postJson(route('gst-compliance.snapshots'), $context)->assertStatus(409);
        $this->assertSame(0, DB::table('mm_gst_periods')->count());
        $this->assertSame(0, DB::table('mm_gst_events')->count());
    }

    public function test_http_plant_context_is_validated_and_cannot_grant_sibling_access(): void
    {
        $this->authenticatePlantUser();
        $this->getJson(route('gst-compliance.data', ['period' => '2026-10', 'plant_id' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('plant_id');
        DB::table('mm_plants')->where('id', 2)->update(['entity_id' => 10]);
        session(['active_plant_id' => 2]);
        $this->getJson(route('gst-compliance.data', ['period' => '2026-10', 'plant_id' => 2]))
            ->assertForbidden();
    }

    public function test_sibling_plants_with_same_gstin_keep_books_and_imports_separate(): void
    {
        DB::table('mm_plants')->where('id', 2)->update(['entity_id' => 10, 'gstin' => '27ABCDE1234F1Z5']);
        $firstBook = $this->book(plant: 1);
        $secondBook = $this->book(plant: 2);
        $raw = GstData::json($this->statement());
        $firstImport = $this->workspace->import(1, '2026-10', 10, $raw);
        $this->assertCount(0, $this->workspace->view(2, '2026-10')['imports']);
        $secondImport = $this->workspace->import(2, '2026-10', 10, $raw);
        $this->assertNotEquals($firstImport->id, $secondImport->id);
        $this->assertNotEquals($firstImport->period_id, $secondImport->period_id);
        foreach ([1 => $firstBook, 2 => $secondBook] as $plant => $book) {
            $view = $this->workspace->view($plant, '2026-10');
            $this->assertSame($plant, $view['plant_id']);
            $this->assertCount(1, $view['books']);
            $this->assertSame('invoice:'.$book, $view['books'][0]['source_key']);
            $this->assertCount(1, $view['imports']);
        }
        $this->authenticatePlantUser();
        $this->getJson(route('gst-compliance.data', ['period' => '2026-10', 'plant_id' => 1]))
            ->assertOk()->assertJsonPath('plant_id', 1)->assertJsonCount(1, 'books')->assertJsonCount(1, 'imports');
    }
}
