<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:24:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Finance;

use App\Services\Gst\GstData;
use Illuminate\Support\Facades\DB;

/** Adapt existing invoice/payment writers to append-only corrections. */
final class SourceJournalService
{
    public function __construct(private readonly AccountingWorkspace $workspace) {}

    private function lines(array $lines): array
    {
        return array_map(function ($l) {
            return ['account_id' => (int) $l['account_id'],
                'debit_amount' => AccountingMoney::decimal(AccountingMoney::units((string) ($l['debit_amount'] ?? '0'))),
                'credit_amount' => AccountingMoney::decimal(AccountingMoney::units((string) ($l['credit_amount'] ?? '0'))),
                'partner_id' => isset($l['partner_id']) ? (int) $l['partner_id'] : null, 'partner_type' => $l['partner_type'] ?? null,
                'tax_id' => isset($l['tax_id']) ? (int) $l['tax_id'] : null,
                'narration_name' => $l['narration_name'] ?? null, 'narration_label' => $l['narration_label'] ?? null, 'line_narration' => $l['line_narration'] ?? null];
        }, $lines);
    }

    public function post(int $plant, int $actor, array $header, array $lines): object
    {
        return $this->workspace->write($plant, function ($p) use ($plant, $actor, $header, $lines) {
            $header = array_intersect_key($header, array_flip(['voucher_type', 'voucher_number', 'ref_module', 'ref_id', 'voucher_date', 'posting_date', 'narration', 'narration_label']));
            $header['voucher_date'] = substr((string) $header['voucher_date'], 0, 10);
            $header['posting_date'] = substr((string) $header['posting_date'], 0, 10);
            validator($header, ['voucher_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'posting_date' => 'required|date_format:Y-m-d|before_or_equal:today'])->validate();
            $this->workspace->openPeriod($plant, substr($header['voucher_date'], 0, 7));
            $lines = $this->lines($lines);
            $digest = GstData::hash([$header, $lines]);
            $existing = DB::table('mm_journal_entries')->where('plant_id', $plant)->where('ref_module', $header['ref_module'])->where('ref_id', $header['ref_id'])->whereNull('deleted_at')->orderByDesc('id')->first();
            if ($existing) {
                $meta = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('journal_id', $existing->id)->first();
                if ($meta && hash_equals($meta->digest, $digest)) {
                    abort_if(DB::table('mm_accounting_journals')->where('reversal_of', $existing->id)->exists(), 409, 'Source was explicitly reversed; create a replacement document');

                    return $this->workspace->entry($plant, $existing->id);
                }
                $this->reverse($plant, $actor, $existing->id, max($header['voucher_date'], substr($existing->voucher_date, 0, 10)), 'Source correction: '.$header['voucher_number'], 'source-reversal:'.$existing->id);
            }
            $number = $header['voucher_number'];
            if ($existing) {
                $number = substr($number, 0, 30).'/R'.$existing->id;
            } else {
                $collision = DB::table('mm_journal_entries')->where('plant_id', $plant)->where('voucher_type', $header['voucher_type'])->where('voucher_number', $number)->first();
                if ($collision && DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('reversal_of', $collision->id)->where('sealed', true)->exists()) {
                    $number = substr($number, 0, 25).'/V'.((int) DB::table('mm_journal_entries')->where('plant_id', $plant)->max('id') + 1);
                }
            }
            abort_if(DB::table('mm_journal_entries')->where('plant_id', $plant)->where('voucher_type', $header['voucher_type'])->where('voucher_number', $number)->exists(), 409, 'Voucher number belongs to another accounting record');
            $header['voucher_number'] = $number;

            return $this->persist($plant, $actor, $p->entity_id, $header, $lines, 'source:'.$header['ref_module'].':'.$header['ref_id'].':'.($existing?->id ?? 'initial'), $digest);
        });
    }

    private function persist(int $plant, int $actor, int $entity, array $header, array $lines, string $key, string $digest, ?int $reversal = null): object
    {
        $dr = 0;
        $cr = 0;
        foreach ($lines as $l) {
            $d = AccountingMoney::units($l['debit_amount']);
            $c = AccountingMoney::units($l['credit_amount']);
            abort_unless($d >= 0 && $c >= 0 && (($d > 0) !== ($c > 0)), 422, 'Source lines must be one-sided and positive');
            abort_unless(DB::table('mm_ledgers')->where('plant_id', $plant)->where('id', $l['account_id'])->whereNull('deleted_at')->where('status', 1)->exists(), 422, 'Source ledger must be active in the document plant');
            $dr += $d;
            $cr += $c;
        }
        abort_unless(count($lines) >= 2 && $dr === $cr && $dr > 0, 422, 'Source journal must balance exactly');
        $id = DB::table('mm_journal_entries')->insertGetId($header + ['plant_id' => $plant, 'entity_id' => $entity, 'total_debit' => AccountingMoney::decimal($dr), 'total_credit' => AccountingMoney::decimal($cr), 'reversal_of_id' => $reversal, 'is_status' => 'POSTED', 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as $l) {
            DB::table('mm_journal_entry_lines')->insert($l + ['plant_id' => $plant, 'journal_entry_id' => $id, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('mm_accounting_journals')->insert(['plant_id' => $plant, 'journal_id' => $id, 'request_key' => $key, 'digest' => $digest, 'reversal_of' => $reversal]);
        DB::table('mm_accounting_journals')->where('journal_id', $id)->update(['sealed' => true]);
        $this->workspace->event($plant, $actor, $reversal ? 'source.reversed' : 'source.posted', ['id' => $id, 'reversal_of' => $reversal, 'digest' => $digest]);

        return $this->workspace->entry($plant, $id);
    }

    public function reverse(int $plant, int $actor, int $id, string $date, string $reason, string $key): object
    {
        return $this->workspace->write($plant, function ($p) use ($plant, $actor, $id, $date, $reason, $key) {
            $j = $this->workspace->entry($plant, $id);
            abort_unless(strtoupper($j->is_status) === 'POSTED', 409, 'Only posted source journals can be reversed');
            $old = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('reversal_of', $id)->first();
            if ($old) {
                abort_unless($old->request_key === $key, 409, 'Source already has a reversal');
                $r = $this->workspace->entry($plant, $old->journal_id);
                abort_unless(substr($r->voucher_date, 0, 10) === $date && $r->narration === $reason, 409, 'Reversal retry changed content');

                return $r;
            }
            $this->workspace->openPeriod($plant, substr($date, 0, 7));
            $lines = $this->lines($j->lines->map(fn ($l) => (array) $l)->all());
            $meta = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('journal_id', $id)->first();
            if (! $meta) {
                DB::table('mm_accounting_journals')->insert(['plant_id' => $plant, 'journal_id' => $id, 'request_key' => 'adopt:'.$id, 'digest' => GstData::hash([(array) $j, $lines])]);
                DB::table('mm_accounting_journals')->where('journal_id', $id)->update(['sealed' => true]);
            }
            foreach ($lines as &$l) {
                [$l['debit_amount'], $l['credit_amount']] = [$l['credit_amount'], $l['debit_amount']];
            } unset($l);
            $header = ['voucher_type' => 'JOURNAL', 'voucher_number' => 'REV-'.$plant.'-'.$id, 'ref_module' => 'accounting_reversal', 'ref_id' => $id, 'voucher_date' => $date, 'posting_date' => $date, 'narration' => $reason];

            return $this->persist($plant, $actor, $p->entity_id, $header, $lines, $key, GstData::hash([$header, $lines]), $id);
        });
    }
}
