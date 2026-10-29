<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:12:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Finance;

use App\Services\Gst\GstData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class AccountingWorkspace
{
    public const ACTIONS = ['VIEW', 'CREATE', 'UPDATE', 'POST', 'REVERSE', 'ADMIN', 'EXPORT', 'TALLY'];

    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    public function write(int $plant, callable $callback): mixed
    {
        return DB::transaction(function () use ($plant, $callback) {
            $p = DB::table('mm_plants')->where('id', $plant)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($p, 404);

            return $callback($p);
        }, 5);
    }

    public function event(int $plant, int $actor, string $action, array $payload): void
    {
        abort_unless($actor > 0, 422, 'An accounting actor is required');
        $previous = DB::table('mm_accounting_events')->where('plant_id', $plant)->orderByDesc('id')->value('digest') ?? '';
        $event = ['plant_id' => $plant, 'actor_id' => $actor, 'action' => $action, 'payload' => GstData::json($payload), 'previous' => $previous, 'created_at' => now()->utc()->format('Y-m-d H:i:s')];
        DB::table('mm_accounting_events')->insert($event + ['digest' => GstData::hash($event)]);
    }

    public function audit(int $plant): array
    {
        $previous = '';
        $count = 0;
        $valid = true;
        foreach (DB::table('mm_accounting_events')->where('plant_id', $plant)->orderBy('id')->cursor() as $row) {
            $event = (array) $row;
            unset($event['id'], $event['digest']);
            $event['plant_id'] = (int) $event['plant_id'];
            $event['actor_id'] = (int) $event['actor_id'];
            $valid = $valid && $previous === $row->previous && hash_equals($row->digest, GstData::hash($event));
            $previous = $row->digest;
            $count++;
        }

        return ['valid' => $valid, 'count' => $count, 'head' => $previous];
    }

    public function account(int $plant, int $actor, array $input, ?int $id = null): object
    {
        $v = Validator::make($input, [
            'ledger_id' => 'nullable|integer|min:1', 'account_type_id' => 'required|integer|min:1',
            'code' => 'required|string|max:50', 'title' => 'required|string|max:255',
            'type' => 'required|in:'.implode(',', self::TYPES), 'parent_id' => 'nullable|integer|min:1',
            'tax_class' => 'nullable|in:input_cgst,input_sgst,input_igst,input_cess,output_cgst,output_sgst,output_igst,output_cess',
            'control' => 'nullable|in:receivable,payable,bank,cash', 'active' => 'required|boolean',
        ])->validate();

        return $this->write($plant, function ($p) use ($plant, $actor, $v, $id) {
            $old = $id ? DB::table('mm_accounting_accounts')->where('plant_id', $plant)->find($id) : null;
            abort_if($id && ! $old, 404);
            $ledger = $old?->ledger_id ?? ($v['ledger_id'] ?? null);
            abort_unless(DB::table('mm_account_types')->where('plant_id', $plant)->whereNull('deleted_at')->where('id', $v['account_type_id'])->exists(), 422, 'Select an account group in this plant');
            abort_if(DB::table('mm_accounting_accounts')->where('plant_id', $plant)->where('code', $v['code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'Account code already exists in this plant');
            abort_if(DB::table('mm_ledgers')->where('plant_id', $plant)->where('code', $v['code'])->when($ledger, fn ($q) => $q->where('id', '!=', $ledger))->exists(), 422, 'Ledger code already exists in this plant');
            if ($v['parent_id'] ?? null) {
                $parent = DB::table('mm_accounting_accounts')->where('plant_id', $plant)->find($v['parent_id']);
                abort_unless($parent && $parent->active && $parent->type === $v['type'], 422, 'Parent must be an active account of the same type in this plant');
                $seen = [];
                while ($parent) {
                    abort_if((int) $parent->id === $id || isset($seen[$parent->id]), 422, 'Account hierarchy cannot contain a cycle');
                    $seen[$parent->id] = true;
                    $parent = $parent->parent_id ? DB::table('mm_accounting_accounts')->where('plant_id', $plant)->find($parent->parent_id) : null;
                }
            }
            if ($ledger) {
                abort_unless(DB::table('mm_ledgers')->where('plant_id', $plant)->whereNull('deleted_at')->where('id', $ledger)->exists(), 404);
                abort_if(! $old && DB::table('mm_accounting_accounts')->where('ledger_id', $ledger)->exists(), 422, 'Ledger is already classified');
                $history = DB::table('mm_journal_entry_lines')->where('plant_id', $plant)->where('account_id', $ledger)->exists();
                abort_if($old && $history && ($old->type !== $v['type'] || $old->tax_class !== ($v['tax_class'] ?? null) || $old->control !== ($v['control'] ?? null)), 409, 'Used account classifications are immutable; create a correction account');
                abort_if(! $v['active'] && $history, 409, 'An account with journal history cannot be deactivated');
            }
            abort_if($id && ! $v['active'] && DB::table('mm_accounting_accounts')->where('plant_id', $plant)->where('parent_id', $id)->where('active', true)->exists(), 409, 'Deactivate child accounts first');
            $data = ['title' => $v['title'], 'code' => $v['code'], 'account_type_id' => $v['account_type_id'], 'is_pnl' => in_array($v['type'], ['income', 'expense']), 'status' => $v['active'] ? 1 : 0];
            if ($ledger) {
                DB::table('mm_ledgers')->where('plant_id', $plant)->where('id', $ledger)->update($data);
            } else {
                $ledger = DB::table('mm_ledgers')->insertGetId($data + ['plant_id' => $plant, 'entity_id' => $p->entity_id, 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            }
            $meta = ['plant_id' => $plant, 'ledger_id' => $ledger, 'code' => $v['code'], 'type' => $v['type'], 'parent_id' => $v['parent_id'] ?? null, 'tax_class' => $v['tax_class'] ?? null, 'control' => $v['control'] ?? null, 'active' => $v['active']];
            if ($old) {
                DB::table('mm_accounting_accounts')->where('id', $id)->update($meta);
            } else {
                $id = DB::table('mm_accounting_accounts')->insertGetId($meta);
            }
            $this->event($plant, $actor, 'account.saved', ['id' => $id, 'before' => $old, 'after' => $meta + ['title' => $v['title']]]);

            return DB::table('mm_accounting_accounts')->find($id);
        });
    }

    public function journal(int $plant, int $actor, array $input, ?int $id = null): object
    {
        $v = Validator::make($input, [
            'request_key' => 'required|string|max:100', 'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'reference' => 'required|string|max:255', 'narration' => 'required|string|max:4000',
            'lines' => 'required|array|min:2|max:200', 'lines.*.account_id' => 'required|integer|min:1',
            'lines.*.debit' => 'required', 'lines.*.credit' => 'required', 'lines.*.narration' => 'nullable|string|max:255',
        ])->validate();
        $dr = 0;
        $cr = 0;
        foreach ($v['lines'] as &$line) {
            $d = AccountingMoney::units($line['debit']);
            $c = AccountingMoney::units($line['credit']);
            abort_unless($d >= 0 && $c >= 0 && (($d > 0) !== ($c > 0)), 422, 'Every line requires exactly one positive debit or credit');
            $dr += $d;
            $cr += $c;
            $line = ['account_id' => (int) $line['account_id'], 'debit' => AccountingMoney::decimal($d), 'credit' => AccountingMoney::decimal($c), 'narration' => $line['narration'] ?? ''];
        }
        unset($line);
        abort_unless($dr === $cr && $dr > 0, 422, 'Journal debits and credits must balance exactly');

        return $this->write($plant, function ($p) use ($plant, $actor, $v, $id, $dr) {
            $digest = GstData::hash($v);
            $prior = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('request_key', $v['request_key'])->first();
            if ($prior && ! $id) {
                abort_unless(hash_equals($prior->digest, $digest), 409, 'Idempotency key was already used with different content');

                return $this->entry($plant, $prior->journal_id);
            }
            $meta = $id ? DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('journal_id', $id)->first() : null;
            abort_if($id && ! $meta, 404);
            abort_if($meta?->sealed, 409, 'Posted journals are immutable; reverse and replace');
            abort_if($prior && (int) $prior->journal_id !== $id, 409, 'Request key already belongs to another journal');
            $this->openPeriod($plant, substr($v['date'], 0, 7));
            $ids = array_unique(array_column($v['lines'], 'account_id'));
            $count = DB::table('mm_accounting_accounts as a')->join('mm_ledgers as l', 'l.id', '=', 'a.ledger_id')
                ->where('a.plant_id', $plant)->where('l.plant_id', $plant)->where('a.active', true)->where('l.status', 1)->whereNull('l.deleted_at')->whereIn('a.ledger_id', $ids)->count();
            abort_unless($count === count($ids), 422, 'Every account must be active and classified in this plant');
            $header = ['voucher_date' => $v['date'], 'posting_date' => $v['date'], 'ref_name' => $v['reference'], 'narration' => $v['narration'], 'total_debit' => AccountingMoney::decimal($dr), 'total_credit' => AccountingMoney::decimal($dr), 'updated_at' => now()];
            if ($id) {
                DB::table('mm_journal_entries')->where('plant_id', $plant)->where('id', $id)->update($header);
                DB::table('mm_journal_entry_lines')->where('plant_id', $plant)->where('journal_entry_id', $id)->delete();
                DB::table('mm_accounting_journals')->where('id', $meta->id)->update(['request_key' => $v['request_key'], 'digest' => $digest]);
            } else {
                // Plant lock serializes allocation; the existing uk_voucher constraint is the final guard.
                $sequence = (int) DB::table('mm_journal_entries')->where('plant_id', $plant)->max('id') + 1;
                do {
                    $number = 'ACC-'.$plant.'-'.str_pad((string) $sequence++, 10, '0', STR_PAD_LEFT);
                } while (DB::table('mm_journal_entries')->where('plant_id', $plant)->where('voucher_type', 'JOURNAL')->where('voucher_number', $number)->exists());
                $id = DB::table('mm_journal_entries')->insertGetId($header + ['plant_id' => $plant, 'entity_id' => $p->entity_id, 'voucher_type' => 'JOURNAL', 'voucher_number' => $number, 'ref_module' => 'accounting', 'is_status' => 'DRAFT', 'created_by' => $actor, 'created_at' => now()]);
                DB::table('mm_accounting_journals')->insert(['plant_id' => $plant, 'journal_id' => $id, 'request_key' => $v['request_key'], 'digest' => $digest]);
            }
            foreach ($v['lines'] as $line) {
                DB::table('mm_journal_entry_lines')->insert(['plant_id' => $plant, 'journal_entry_id' => $id, 'account_id' => $line['account_id'], 'debit_amount' => $line['debit'], 'credit_amount' => $line['credit'], 'line_narration' => $line['narration'], 'created_by' => $actor, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->event($plant, $actor, 'journal.draft', ['id' => $id, 'content' => $v]);

            return $this->entry($plant, $id);
        });
    }

    public function openPeriod(int $plant, string $period): void
    {
        GstData::period($period);
        abort_if(DB::table('mm_accounting_periods')->where(['plant_id' => $plant, 'period' => $period, 'closed' => true])->exists(), 409, 'Accounting month is closed');
    }

    public function entry(int $plant, int $id): object
    {
        $j = DB::table('mm_journal_entries')->where('plant_id', $plant)->whereNull('deleted_at')->find($id);
        abort_unless($j, 404);
        $j->lines = DB::table('mm_journal_entry_lines')->where('plant_id', $plant)->where('journal_entry_id', $id)->whereNull('deleted_at')->orderBy('id')->get();
        $j->reversed_by = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('reversal_of', $id)->where('sealed', true)->value('journal_id');
        $j->display_status = $j->reversed_by ? 'REVERSED' : $j->is_status;

        return $j;
    }

    public function sealExisting(int $plant, int $actor, int $id): void
    {
        $this->write($plant, function () use ($plant, $actor, $id) {
            $j = $this->entry($plant, $id);
            $meta = DB::table('mm_accounting_journals')->where('journal_id', $id)->first();
            if ($meta?->sealed) {
                return;
            }
            $this->openPeriod($plant, substr($j->voucher_date, 0, 7));
            if (! $meta) {
                DB::table('mm_accounting_journals')->insert(['plant_id' => $plant, 'journal_id' => $id, 'request_key' => 'adopt:'.$id, 'digest' => GstData::hash(json_decode(json_encode($j, JSON_THROW_ON_ERROR), true)), 'reversal_of' => $j->reversal_of_id ?? null]);
            }
            DB::table('mm_accounting_journals')->where('journal_id', $id)->update(['sealed' => true]);
            $this->event($plant, $actor, 'journal.sealed', ['id' => $id]);
        });
    }

    public function post(int $plant, int $actor, int $id): object
    {
        return $this->write($plant, function () use ($plant, $actor, $id) {
            $meta = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('journal_id', $id)->first();
            abort_unless($meta, 404);
            $j = $this->entry($plant, $id);
            if ($meta->sealed) {
                return $j;
            }
            $this->openPeriod($plant, substr($j->voucher_date, 0, 7));
            $dr = 0;
            $cr = 0;
            foreach ($j->lines as $l) {
                $d = AccountingMoney::units((string) $l->debit_amount);
                $c = AccountingMoney::units((string) $l->credit_amount);
                abort_unless($d >= 0 && $c >= 0 && (($d > 0) !== ($c > 0)), 422, 'Invalid journal line');
                abort_unless(DB::table('mm_accounting_accounts as a')->join('mm_ledgers as l', 'l.id', '=', 'a.ledger_id')->where('a.plant_id', $plant)->where('l.plant_id', $plant)->where('a.ledger_id', $l->account_id)->where('a.active', true)->where('l.status', 1)->whereNull('l.deleted_at')->exists(), 422, 'An account is no longer available');
                $dr += $d;
                $cr += $c;
            }
            abort_unless(count($j->lines) >= 2 && $dr > 0 && $dr === $cr && $dr === AccountingMoney::units((string) $j->total_debit) && $cr === AccountingMoney::units((string) $j->total_credit), 422, 'Journal header and lines must balance');
            DB::table('mm_journal_entries')->where('id', $id)->update(['is_status' => 'POSTED', 'updated_by' => $actor, 'updated_at' => now()]);
            DB::table('mm_accounting_journals')->where('id', $meta->id)->update(['sealed' => true]);
            $this->event($plant, $actor, 'journal.posted', ['id' => $id, 'total' => AccountingMoney::decimal($dr)]);

            return $this->entry($plant, $id);
        });
    }

    public function reverse(int $plant, int $actor, int $id, array $input): object
    {
        $v = Validator::make($input, ['date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|min:10|max:2000', 'request_key' => 'required|string|max:100'])->validate();

        return $this->write($plant, function () use ($plant, $actor, $id, $v) {
            $j = $this->entry($plant, $id);
            abort_unless(strtoupper($j->is_status) === 'POSTED', 409, 'Only posted journals can be reversed');
            abort_if($v['date'] < substr($j->voucher_date, 0, 10), 422, 'Reversal cannot predate its original');
            if ($j->ref_module !== 'accounting') {
                return app(SourceJournalService::class)->reverse($plant, $actor, $id, $v['date'], $v['reason'], $v['request_key']);
            }
            $old = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('reversal_of', $id)->first();
            if ($old) {
                abort_unless($old->request_key === $v['request_key'], 409, 'Journal already has a reversal');
                $existing = $this->entry($plant, $old->journal_id);
                abort_unless(substr($existing->voucher_date, 0, 10) === $v['date'] && $existing->narration === $v['reason'], 409, 'Reversal retry changed content');

                return $existing;
            }
            abort_if(DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('request_key', $v['request_key'])->exists(), 409, 'Use a new reversal request key');
            $r = $this->journal($plant, $actor, ['request_key' => $v['request_key'], 'date' => $v['date'], 'reference' => 'Reversal of '.$j->voucher_number, 'narration' => $v['reason'], 'lines' => $j->lines->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => (string) $l->credit_amount, 'credit' => (string) $l->debit_amount])->all()]);
            DB::table('mm_journal_entries')->where('id', $r->id)->update(['reversal_of_id' => $id]);
            DB::table('mm_accounting_journals')->where('journal_id', $r->id)->update(['reversal_of' => $id]);
            $this->post($plant, $actor, $r->id);
            $this->event($plant, $actor, 'journal.reversed', ['original' => $id, 'reversal' => $r->id, 'reason' => $v['reason']]);

            return $this->entry($plant, $r->id);
        });
    }

    public function deleteDraft(int $plant, int $actor, int $id): void
    {
        $this->write($plant, function () use ($plant, $actor, $id) {
            $m = DB::table('mm_accounting_journals')->where('plant_id', $plant)->where('journal_id', $id)->first();
            abort_unless($m, 404);
            abort_if($m->sealed, 409, 'Posted journals cannot be deleted');
            DB::table('mm_journal_entry_lines')->where('plant_id', $plant)->where('journal_entry_id', $id)->update(['deleted_at' => now(), 'is_deleted' => 1]);
            DB::table('mm_journal_entries')->where('plant_id', $plant)->where('id', $id)->update(['deleted_at' => now(), 'is_deleted' => 1]);
            $this->event($plant, $actor, 'journal.draft_deleted', ['id' => $id]);
        });
    }
}
