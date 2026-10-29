<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:20:53 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Gst;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

final class GstWorkspace
{
    public function __construct(private readonly GstBooks $books, private readonly GstMatcher $matcher) {}

    private function state(int $plant, string $period): ?object
    {
        return DB::table('mm_gst_periods')->where(['plant_id' => $plant, 'period' => $period])->first();
    }

    private function mutate(int $plant, string $period, int $actor, callable $callback): mixed
    {
        GstData::period($period);
        abort_unless($actor > 0, 401);

        return DB::transaction(function () use ($plant, $period, $callback) {
            // Match invoice creation's plant lock; new module writes are also serialised by period.
            $p = DB::table('mm_plants')->where('id', $plant)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($p, 404);
            $gstin = strtoupper(trim($p->gstin ?? ''));
            abort_unless(preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin), 422, 'Configure and verify the active plant GSTIN first');
            DB::table('mm_gst_periods')->insertOrIgnore(['plant_id' => $plant, 'period' => $period, 'gstin' => $gstin]);
            DB::table('mm_gst_periods')->where(['plant_id' => $plant, 'period' => $period])->increment('revision');
            $state = $this->state($plant, $period);
            abort_unless($state->gstin === $gstin, 409, 'GSTIN changed; retain existing evidence and resolve registration scope');

            return $callback($state);
        }, 5);
    }

    private function append(string $table, object $state, int $actor, array $payload, array $extra = []): object
    {
        $id = DB::table('mm_gst_'.$table)->insertGetId($extra + ['period_id' => $state->id, 'created_by' => $actor, 'created_at' => now(), 'payload' => GstData::json($payload), 'digest' => GstData::hash($payload)]);
        $s = DB::table('mm_gst_periods')->find($state->id);
        $event = ['action' => $table.'.created', 'record_id' => $id, 'record_digest' => $extra['digest'] ?? GstData::hash($payload), 'record_payload_hash' => GstData::hash($payload), 'actor' => $actor, 'at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'period_id' => $s->id, 'sequence' => $s->audit_sequence + 1, 'previous' => $s->audit_head];
        $hash = GstData::hash($event);
        DB::table('mm_gst_events')->insert(['period_id' => $s->id, 'created_by' => $actor, 'created_at' => now(), 'payload' => GstData::json($event), 'digest' => $hash, 'sequence' => $s->audit_sequence + 1, 'previous' => $s->audit_head]);
        DB::table('mm_gst_periods')->where('id', $s->id)->update(['audit_head' => $hash, 'audit_sequence' => $s->audit_sequence + 1]);

        return DB::table('mm_gst_'.$table)->find($id);
    }

    private function records(string $table, ?object $state): array
    {
        if (! $state) {
            return [];
        }

        return DB::table('mm_gst_'.$table)->where('period_id', $state->id)->orderBy('id')->get()->map(function ($r) {
            $r->data = json_decode($r->payload, true, 512, JSON_THROW_ON_ERROR);
            unset($r->payload,$r->raw);

            return $r;
        })->all();
    }

    private function latestReview(array $reviews, string $type, int $id): ?object
    {
        return collect($reviews)->where('target_type', $type)->where('target_id', $id)->last() ?: null;
    }

    private function inputs(int $plant, string $period): array
    {
        $state = $this->state($plant, $period);
        $data = ['plant_id' => $plant, 'period' => $period, 'gstin' => DB::table('mm_plants')->where('id', $plant)->value('gstin'), 'books' => $this->books->period($plant, $period),
            'imports' => $this->records('imports', $state), 'rules' => $this->records('rules', $state), 'classifications' => $this->records('classifications', $state),
            'reviews' => array_values(array_filter($this->records('reviews', $state), fn ($r) => $r->target_type !== 'snapshot'))];
        $data['latest_statement'] = collect($data['imports'])->last()?->data;
        $data['ledger_hash'] = $this->books->ledgerHash($plant, $period);
        $data['imports'] = array_map(fn ($r) => ['id' => $r->id, 'digest' => $r->digest, 'created_by' => $r->created_by, 'created_at' => $r->created_at], $data['imports']);

        // Canonical hashes must receive arrays, including nested query results.
        return json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function import(int $plant, string $period, int $actor, string $raw): object
    {
        abort_if(strlen($raw) > 10 * 1024 * 1024, 413);
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'Invalid JSON');
        }
        $v = Validator::make(is_array($input) ? $input : [], [
            'schema' => 'required|in:modormc.2b.v1', 'gstin' => 'required|string|size:15', 'period' => 'required|string',
            'source_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/', 'not_in:'.str_repeat('0', 64)], 'generated_at' => 'required|date',
            'documents' => 'present|array|list|max:20000', 'documents.*' => 'required|array',
        ])->validate();
        abort_unless($v['period'] === $period, 422, 'Statement period mismatch');
        $v['documents'] = array_map(fn ($r) => GstData::invoice($r), $v['documents']);

        return $this->mutate($plant, $period, $actor, function ($state) use ($v, $actor, $raw) {
            abort_unless($v['gstin'] === $state->gstin, 422, 'Recipient GSTIN mismatch');
            $digest = hash('sha256', $raw);
            if ($old = DB::table('mm_gst_imports')->where(['period_id' => $state->id, 'digest' => $digest])->first()) {
                return $old;
            }

            // digest identifies exact raw bytes, event contains the normalized payload hash.
            return $this->append('imports', $state, $actor, $v, ['raw' => $raw, 'digest' => $digest]);
        });
    }

    public function classify(int $plant, string $period, int $actor, array $input): object
    {
        if (str_starts_with((string) ($input['source_key'] ?? ''), 'adjustment:')) {
            $input['source_hash'] = GstData::hash(['adjustment' => $input['source_key'], 'period' => $period]);
        }
        $v = Validator::make($input, [
            'source_key' => 'required|string|max:120', 'source_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'reason' => 'required|string|min:10|max:4000',
            'supplier_document'=>'sometimes|array:number,date,supplier_gstin,kind,reverse_charge',
            'supplier_document.number'=>['required_with:supplier_document','string','max:100','regex:/[A-Za-z0-9]/'],
            'supplier_document.date'=>'required_with:supplier_document|date_format:Y-m-d',
            'supplier_document.supplier_gstin'=>['required_with:supplier_document','regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'supplier_document.kind'=>'required_with:supplier_document|in:INV,CN,DN',
            'supplier_document.reverse_charge'=>'required_with:supplier_document|boolean',
            'allocations' => 'required|array|min:1|max:50', 'allocations.*' => 'required|array:row,amounts,pos,recipient,advance,liability_period',
            'allocations.*.row' => 'required|in:'.implode(',', GstData::ROWS), 'allocations.*.amounts' => 'required|array',
            'allocations.*.pos' => ['nullable', 'regex:/^[0-9]{2}$/'], 'allocations.*.recipient' => 'nullable|in:registered,unregistered,composition,uin',
            'allocations.*.advance' => 'sometimes|boolean', 'allocations.*.liability_period' => 'sometimes|string',
        ])->validate();
        foreach ($v['allocations'] as &$a) {
            $a['amounts'] = GstData::amounts($a['amounts']);
            $a['liability_period'] = $a['liability_period'] ?? $period;
            GstData::period($a['liability_period']);
            abort_if($a['liability_period'] > $period, 422, 'Future liability period');
            abort_if(isset($a['recipient']) && $a['recipient'] !== 'registered' && ! isset($a['pos']), 422, 'POS is required for recipient-class disclosure');
        } unset($a);

        return $this->mutate($plant, $period, $actor, function ($state) use ($plant, $period, $actor, $v) {
            $books = collect($this->books->period($plant, $period))->keyBy('source_key');
            if (str_starts_with($v['source_key'], 'adjustment:')) {
                abort_unless($v['source_hash'] === GstData::hash(['adjustment' => $v['source_key'], 'period' => $period]), 422, 'Adjustment evidence identity mismatch');
            } else {
                abort_unless(isset($books[$v['source_key']]) && hash_equals($books[$v['source_key']]['hash'], $v['source_hash']), 409, 'Book source changed; refresh before classifying');
            }
            $last = DB::table('mm_gst_classifications')->where(['period_id' => $state->id, 'source_key' => $v['source_key']])->orderByDesc('id')->first();
            if ($last && $last->digest === GstData::hash($v)) {
                return $last;
            }

            return $this->append('classifications', $state, $actor, $v, ['source_key' => $v['source_key'], 'source_hash' => $v['source_hash']]);
        });
    }

    public function rule(int $plant, string $period, int $actor, array $input): object
    {
        $v = Validator::make($input, ['version' => 'required|string|max:100', 'reason' => 'required|string|min:10|max:4000', 'policy' => 'sometimes|array'])->validate();
        $policy = $v['policy'] ?? config('gst_compliance.rule_template');
        Validator::make($policy, ['engine' => 'required|in:reviewed-gst-v1', 'verified_on' => 'required|date_format:Y-m-d', 'from_period' => 'required|string', 'to_period' => 'required|string', 'sources' => 'required|array|min:1', 'sources.*' => 'required|url|max:1000'])->validate();
        GstData::period($policy['from_period']);
        GstData::period($policy['to_period']);
        abort_unless($policy['from_period'] >= '2025-10' && $policy['from_period'] <= $period && $policy['to_period'] >= $period, 422, 'Rule does not cover this engine/period');
        $v['policy'] = $policy;

        return $this->mutate($plant, $period, $actor, function ($state) use ($actor, $v) {
            $old = DB::table('mm_gst_rules')->where(['period_id' => $state->id, 'version' => $v['version']])->first();
            if ($old) {
                abort_unless($old->digest === GstData::hash($v), 409, 'Version already used for another policy');

                return $old;
            }

            return $this->append('rules', $state, $actor, $v, ['version' => $v['version']]);
        });
    }

    public function stageInbound(int $plant, string $period, int $actor, array $input): object
    {
        $v = Validator::make($input, [
            'remote_guid' => 'required|string|max:128', 'date' => 'required|date_format:Y-m-d', 'narration' => 'required|string|max:255',
            'reason' => 'required|string|min:10|max:4000', 'lines' => 'required|array|min:2|max:500', 'lines.*' => 'required|array:account_id,debit,credit',
            'lines.*.account_id' => 'required|integer|min:1', 'lines.*.debit' => 'required|integer|min:0|max:1000000000000', 'lines.*.credit' => 'required|integer|min:0|max:1000000000000',
        ])->validate();
        abort_unless(substr($v['date'], 0, 7) === $period, 422, 'Voucher date must belong to this reporting month');
        $dr = 0;
        $cr = 0;
        foreach ($v['lines'] as &$l) {
            abort_unless(is_int($l['debit']) && is_int($l['credit']) && (($l['debit'] > 0) !== ($l['credit'] > 0)), 422, 'Use one positive integer-paise side per line');
            $l['account_id'] = (int) $l['account_id'];
            $dr += $l['debit'];
            $cr += $l['credit'];
        }unset($l);
        abort_unless($dr === $cr && $dr > 0, 422, 'Inbound voucher must balance');

        return $this->mutate($plant, $period, $actor, function ($state) use ($plant, $actor, $v) {
            $versions = DB::table('mm_gst_inbound')->where(['plant_id' => $plant, 'remote_guid' => $v['remote_guid']])->orderBy('id')->get();
            $old = $versions->last();
            if ($old) {
                if ($old->period_id === $state->id && $old->digest === GstData::hash($v)) {
                    return $old;
                }
                abort_if(DB::table('mm_gst_reviews')->where('target_type', 'inbound')->whereIn('target_id', $versions->pluck('id'))->where('decision', 'include')->exists(), 409, 'Remote voucher already posted; reverse/correct explicitly');
                // Never reactivate an old superseded payload silently.
                abort_if($versions->contains(fn ($r) => $r->digest === GstData::hash($v)), 409, 'Payload matches a superseded mapping; create an explicitly corrected revision');
            }
            $export = app(TallyJournalExport::class);
            foreach (DB::table('mm_journal_entries')->where('plant_id', $plant)->pluck('id') as $id) {
                abort_if($export->guid($plant, $id) === $v['remote_guid'], 409, 'Echo of a web-origin voucher');
            }

            return $this->append('inbound', $state, $actor, $v, ['plant_id' => $plant, 'remote_guid' => $v['remote_guid']]);
        });
    }

    /** Runs only inside the plant/period transaction and after independent review. */
    private function postInbound(int $plant, int $actor, object $record): int
    {
        $v = json_decode($record->payload, true, 512, JSON_THROW_ON_ERROR);
        $ids = array_unique(array_column($v['lines'], 'account_id'));
        abort_unless(DB::table('mm_ledgers')->where('plant_id', $plant)->whereNull('deleted_at')->whereIn('id', $ids)->count() === count($ids), 422, 'Map every Tally ledger to a current ledger in this plant');
        $voucher = 'TALLY/'.$record->id;
        abort_if(JournalEntry::withTrashed()->where(['plant_id' => $plant, 'voucher_type' => 'JOURNAL', 'voucher_number' => $voucher])->exists(), 409, 'Inbound voucher number already used');
        $money = fn (int $n) => intdiv($n, 100).'.'.str_pad((string) ($n % 100), 2, '0', STR_PAD_LEFT);
        $total = array_sum(array_column($v['lines'], 'debit'));
        $entry = JournalEntry::create(['plant_id' => $plant, 'entity_id' => DB::table('mm_plants')->where('id', $plant)->value('entity_id'),
            'voucher_type' => 'JOURNAL', 'voucher_number' => $voucher, 'ref_module' => 'tally_import', 'ref_id' => $record->id, 'voucher_date' => $v['date'], 'posting_date' => $v['date'],
            'narration' => $v['narration'], 'narration_label' => 'Tally Import', 'total_debit' => $money($total), 'total_credit' => $money($total), 'is_status' => 'POSTED', 'created_by' => $actor]);
        foreach ($v['lines'] as $line) {
            JournalEntryLine::create(['journal_entry_id' => $entry->id, 'plant_id' => $plant, 'account_id' => $line['account_id'],
                'debit_amount' => $money($line['debit']), 'credit_amount' => $money($line['credit']), 'narration_name' => 'Tally Import', 'narration_label' => 'Tally Import', 'line_narration' => $v['reason'], 'created_by' => $actor]);
        }

        if (Schema::hasTable('mm_accounting_journals')) app(\App\Services\Finance\AccountingWorkspace::class)->sealExisting($plant, $actor, (int) $entry->id);

        return (int) $entry->id;
    }

    public function review(int $plant, string $period, int $actor, string $type, int $target, array $input): object
    {
        abort_unless(in_array($type, ['rule', 'classification', 'snapshot', 'inbound'], true), 422);
        $v = Validator::make($input, ['decision' => 'required|in:include,exclude', 'reason' => 'required|string|min:10|max:4000', 'checklist' => 'sometimes|array'])->validate();

        return $this->mutate($plant, $period, $actor, function ($state) use ($plant, $period, $actor, $type, $target, $v) {
            $table = ['rule' => 'rules', 'classification' => 'classifications', 'snapshot' => 'snapshots', 'inbound' => 'inbound'][$type];
            $row = DB::table('mm_gst_'.$table)->where(['period_id' => $state->id, 'id' => $target])->first();
            abort_unless($row, 404);
            abort_if((int) $row->created_by === $actor, 403, 'A different user must review this record');
            if ($type === 'inbound') {
                $latest = DB::table('mm_gst_inbound')->where(['plant_id' => $plant, 'remote_guid' => $row->remote_guid])->max('id');
                abort_unless((int) $latest === (int) $row->id, 409, 'A newer inbound mapping revision exists');
                abort_if(DB::table('mm_gst_reviews')->where(['period_id' => $state->id, 'target_type' => 'inbound', 'target_id' => $target, 'decision' => 'include'])->exists(), 409, 'Already posted');
                if ($v['decision'] === 'include') {
                    $v['journal_entry_id'] = $this->postInbound($plant, $actor, $row);
                }
            }
            if ($type === 'snapshot') {
                abort_unless($v['decision'] === 'include', 422, 'Snapshots can only be approved; prepare a replacement to correct one');
                abort_if(DB::table('mm_gst_reviews')->where(['period_id' => $state->id, 'target_type' => 'snapshot', 'target_id' => $target])->exists(), 409, 'Already approved');
                $payload = json_decode($row->payload, true);
                abort_unless($payload['blockers'] === [], 422, 'Resolve preparation blockers');
                abort_unless(hash_equals($row->inputs_hash, GstData::hash($this->inputs($plant, $period))), 409, 'Source data changed; prepare a new worksheet');
                foreach (GstData::CHECKS as $key => $label) {
                    abort_unless(($v['checklist'][$key] ?? null) === true, 422, 'Confirm: '.$label);
                }
            }

            return $this->append('reviews', $state, $actor, $v, ['target_type' => $type, 'target_id' => $target, 'decision' => $v['decision']]);
        });
    }

    public function prepare(int $plant, string $period, int $actor): object
    {
        return $this->mutate($plant, $period, $actor, function ($state) use ($plant, $period, $actor) {
            $inputs = $this->inputs($plant, $period);
            $rows = [];
            foreach (GstData::ROWS as $r) {
                $rows[$r] = array_fill_keys(GstData::HEADS, 0);
            }
            $reviews = $this->records('reviews', $state);
            $rules = $this->records('rules', $state);
            $rule = collect($rules)->last();
            $blockers = [];
            $pos = [];
            $breakup = [];
            if (! $rule || $this->latestReview($reviews, 'rule', $rule->id)?->decision !== 'include') {
                $blockers[] = 'Latest tax rule requires independent approval';
            }
            $facts = collect($this->records('classifications', $state))->groupBy('source_key')->map(fn ($rs) => $rs->last());
            $keys = array_column($inputs['books'], 'source_key');
            foreach ($inputs['books'] as $book) {
                $fact = $facts->get($book['source_key']);
                if (! $fact || ! hash_equals($book['hash'], $fact->source_hash)) {
                    $blockers[] = $book['source_key'].': classify current document';
                }
            }
            foreach ($facts as $key => $fact) {
                $review = $this->latestReview($reviews, 'classification', $fact->id);
                if (! in_array($key, $keys, true) && ! str_starts_with($key, 'adjustment:')) {
                    if ($review?->decision !== 'exclude') {
                        $blockers[] = $key.': source removed or moved to another period; independently exclude the old classification';
                    }

                    continue;
                }
                if (! $review) {
                    $blockers[] = $key.': independent classification review required';

                    continue;
                }
                if ($review->decision !== 'include') {
                    continue;
                }
                foreach ($fact->data['allocations'] as $a) {
                    foreach (GstData::HEADS as $h) {
                        $rows[$a['row']][$h] += $a['amounts'][$h];
                    }
                    if (in_array($a['row'], ['3.1.a', '3.1.b', '3.1.d', '3.1.1.i'], true)) {
                        $lp = $a['liability_period'];
                        $breakup[$lp] ??= array_fill_keys(GstData::HEADS, 0);
                        foreach (GstData::HEADS as $h) {
                            $breakup[$lp][$h] += $a['amounts'][$h];
                        }
                    }
                    if (in_array($a['row'], ['3.1.a', '3.1.1.i'], true) && ! ($a['advance'] ?? false) && isset($a['pos']) && substr($state->gstin, 0, 2) !== $a['pos'] && in_array($a['recipient'] ?? '', ['unregistered', 'composition', 'uin'], true)) {
                        $k = $a['pos'].':'.$a['recipient'];
                        $pos[$k] ??= ['pos' => $a['pos'], 'recipient' => $a['recipient'], 'taxable' => 0, 'igst' => 0];
                        $pos[$k]['taxable'] += $a['amounts']['taxable'];
                        $pos[$k]['igst'] += $a['amounts']['igst'];
                    }
                }
            }
            $rows['4C'] = array_fill_keys(GstData::HEADS, 0);
            foreach (['igst', 'cgst', 'sgst', 'cess'] as $h) {
                foreach (['4A1', '4A2', '4A3', '4A4', '4A5'] as $r) {
                    $rows['4C'][$h] += $rows[$r][$h];
                } $rows['4C'][$h] -= $rows['4B1'][$h] + $rows['4B2'][$h];
            }
            $payload = ['format' => 'modormc.gstr3b.worksheet.v1', 'period' => $period, 'gstin' => $state->gstin, 'unit' => 'paise', 'rows' => $rows, 'table32' => array_values($pos),
                'liability_breakup' => $breakup, 'blockers' => array_values(array_unique($blockers)), 'checklist' => GstData::CHECKS, 'rule_id' => $rule?->id, 'inputs' => $inputs,
                'warnings' => ['Plant-scoped worksheet: consolidate all branches for the GSTIN.', 'Manual payment setoff, interest, eligibility and portal comparison required.', 'Book values are signed; verify negative-value portal treatment.']];

            return $this->append('snapshots', $state, $actor, $payload, ['inputs_hash' => GstData::hash($inputs)]);
        });
    }

    public function view(int $plant, string $period): array
    {
        GstData::period($period);
        $state = $this->state($plant, $period);
        $inputs = $this->inputs($plant, $period);
        $imports = $this->records('imports', $state);
        $last = collect($imports)->last();
        $facts=collect($this->records('classifications',$state))->groupBy('source_key')->map(fn($rs)=>$rs->last());
        $matchBooks=[];$referenceRequired=[];
        foreach($inputs['books'] as $book){
            if($book['direction']!=='purchase')continue;
            $fact=$facts->get($book['source_key']);
            if($fact&&hash_equals($book['hash'],$fact->source_hash)&&isset($fact->data['supplier_document'])){
                $identity=$fact->data['supplier_document'];$identity['reverse_charge']=(bool)$identity['reverse_charge'];$matchBooks[]=array_replace($book,$identity);
            }else $referenceRequired[]=['source_key'=>$book['source_key'],'number'=>$book['number'],'status'=>'supplier_reference_required','candidate_count'=>0,'statement_indices'=>[],'delta'=>[]];
        }
        $snapshots = $this->records('snapshots', $state);
        $reviews = $this->records('reviews', $state);
        foreach ($snapshots as $s) {
            $s->stale = ! hash_equals($s->inputs_hash, GstData::hash($inputs));
            $s->approval = $this->latestReview($reviews, 'snapshot', $s->id);
            unset($s->data['inputs']);
        }

        return ['plant_id' => $plant, 'period' => $period, 'gstin' => $inputs['gstin'], 'books' => $inputs['books'], 'imports' => array_map(fn ($r) => ['id' => $r->id, 'created_at' => $r->created_at, 'digest' => $r->digest, 'count' => count($r->data['documents'])], $imports),
            'matches' => array_merge($this->matcher->match($matchBooks,$last?->data['documents']??[]),$referenceRequired),
            'rules' => $this->records('rules', $state), 'classifications' => $this->records('classifications', $state), 'reviews' => $reviews, 'snapshots' => $snapshots, 'inbound' => $this->records('inbound', $state),
            'ledgers' => Schema::hasTable('mm_ledgers') ? DB::table('mm_ledgers')->where('plant_id', $plant)->whereNull('deleted_at')->get(['id', 'title']) : [], 'checklist' => GstData::CHECKS,
            'rows' => GstData::ROWS, 'audit' => ['valid' => null, 'head' => $state?->audit_head ?? '', 'sequence' => $state?->audit_sequence ?? 0]];
    }

    public function verify(int $plant, string $period): array
    {
        return $this->audit($this->state($plant, GstData::period($period)));
    }

    public function audit(?object $state): array
    {
        if (! $state) {
            return ['valid' => true, 'head' => '', 'sequence' => 0];
        } $prev = '';
        $seq = 0;
        $records = [];
        foreach (['imports', 'rules', 'classifications', 'reviews', 'snapshots', 'inbound'] as $table) {
            $records[$table] = DB::table('mm_gst_'.$table)->where('period_id', $state->id)->get()->keyBy('id');
        }
        foreach (DB::table('mm_gst_events')->where('period_id', $state->id)->orderBy('sequence')->get() as $r) {
            $p = json_decode($r->payload, true);
            if ($r->previous !== $prev || (int) $r->sequence !== ++$seq || $p['previous'] !== $prev || $p['sequence'] !== $seq || ! hash_equals($r->digest, GstData::hash($p))) {
                return ['valid' => false, 'sequence' => $seq];
            } $prev = $r->digest;
            $table = explode('.', $p['action'])[0];
            if (! in_array($table, ['imports', 'rules', 'classifications', 'reviews', 'snapshots', 'inbound'], true)) {
                return ['valid' => false, 'sequence' => $seq];
            }
            $record = $records[$table]->get($p['record_id']);
            if (! $record || ! hash_equals($p['record_digest'], $record->digest) || ! hash_equals($p['record_payload_hash'], GstData::hash(json_decode($record->payload, true)))) {
                return ['valid' => false, 'sequence' => $seq];
            }
            if ($table === 'imports' && ! hash_equals($record->digest, hash('sha256', $record->raw))) {
                return ['valid' => false, 'sequence' => $seq];
            }
        }

        return ['valid' => $state->audit_head === $prev && (int) $state->audit_sequence === $seq, 'head' => $prev, 'sequence' => $seq];
    }

    public function snapshot(int $plant, string $period, int $id): object
    {
        $s = $this->state($plant, $period);
        abort_unless($s,404);
        $r = DB::table('mm_gst_snapshots')->where(['period_id' => $s->id, 'id' => $id])->first();
        abort_unless($r,404);

        return $r;
    }
}
