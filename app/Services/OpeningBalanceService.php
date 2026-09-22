<?php

namespace App\Services;

use App\Models\{JournalEntry, Ledger, OpeningBalanceBatch, Patron, Plant};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    public function validate(array $input, int $plantId): array
    {
        $data = Validator::make($input, [
            'cutover_date' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'regex:/^\d{4}-04-01$/'],
            'clearing_account_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:2000',
            'lines.*.account_id' => 'required|integer',
            'lines.*.partner_id' => 'nullable|integer',
            'lines.*.side' => 'required|in:Dr,Cr',
            'lines.*.amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'lines.*.reference' => 'nullable|string|max:200',
        ], ['cutover_date.regex' => 'Opening balance date must be April 1 of the selected financial year (YYYY-04-01).'])->validate();

        $ledgers = Ledger::withoutGlobalScope('plant_id')->where('plant_id', $plantId)->get()->keyBy('id');
        $patrons = Patron::withoutGlobalScope('plant_id')->where('plant_id', $plantId)->get()->keyBy('id');
        $controls = $patrons->pluck('debit_ledger_id')->merge($patrons->pluck('credit_ledger_id'))
            ->merge(collect($data['lines'])->filter(fn ($l) => !empty($l['partner_id']))->pluck('account_id'))->filter()->unique();
        $seen = [];
        foreach ($data['lines'] as $i => &$line) {
            $ledger = $ledgers->get($line['account_id']);
            if (!$ledger || !$ledger->status) {
                $this->error("lines.$i.account_id", 'Select an active ledger belonging to this plant.');
            }
            $line['partner_id'] = !empty($line['partner_id']) ? (int) $line['partner_id'] : null;
            $line['account_id'] = (int) $line['account_id'];
            if ($line['partner_id'] && !$patrons->has($line['partner_id'])) {
                $this->error("lines.$i.partner_id", 'Select an active patron belonging to this plant.');
            }
            if (!$line['partner_id'] && $controls->contains($line['account_id'])) {
                $this->error("lines.$i.account_id", 'This is a patron control ledger. Enter its balance against individual patrons only.');
            }
            $key = $line['account_id'].':'.($line['partner_id'] ?? 0);
            if (isset($seen[$key])) {
                $this->error("lines.$i.account_id", 'Duplicate ledger/patron balance. Enter one net opening amount per combination.');
            }
            $seen[$key] = true;
            $cents = self::cents((string) $line['amount']);
            if ($cents <= 0) {
                $this->error("lines.$i.amount", 'Opening amounts must be greater than zero.');
            }
            $line['amount'] = self::decimal($cents);
        }
        unset($line);
        if (!empty($data['clearing_account_id'])) {
            $clearing = $ledgers->get($data['clearing_account_id']);
            if (!$clearing || !$clearing->status || $clearing->is_pnl || $controls->contains($clearing->id)
                || collect($data['lines'])->contains('account_id', $clearing->id)) {
                $this->error('clearing_account_id', 'Choose an active balance-sheet clearing ledger, separate from patron accounts and entered balances.');
            }
        }
        return $data;
    }

    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function save(array $input, int $plantId, int $userId, int $version): OpeningBalanceBatch
    {
        return DB::transaction(function () use ($input, $plantId, $userId, $version) {
            // Serialize even the first draft creation, when no batch row exists yet.
            Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $batch = OpeningBalanceBatch::where('plant_id', $plantId)->lockForUpdate()->first();
            abort_if($batch && $batch->status === 'POSTED', 409, 'Reverse the posted setup before changing it.');
            abort_if(($batch?->version ?? 0) !== $version, 409, 'This setup changed in another session. Reload before saving.');
            $data = $this->validate($input, $plantId);
            $before = $batch?->toArray();
            $batch ??= new OpeningBalanceBatch(['plant_id' => $plantId, 'created_by' => $userId]);
            $batch->fill($data + ['updated_by' => $userId, 'version' => $version + 1])->save();
            $this->audit($batch, $userId, $before ? 'UPDATE' : 'CREATE', $before);
            return $batch->fresh();
        });
    }

    public function post(int $plantId, int $userId, int $version): OpeningBalanceBatch
    {
        return DB::transaction(function () use ($plantId, $userId, $version) {
            $plant = Plant::whereKey($plantId)->lockForUpdate()->firstOrFail();
            $batch = OpeningBalanceBatch::where('plant_id', $plantId)->lockForUpdate()->firstOrFail();
            // A retried post returns the existing result; it never creates another journal.
            if ($batch->status === 'POSTED' && $batch->version === $version + 1) {
                return $batch;
            }
            abort_if($batch->status !== 'DRAFT' || $batch->version !== $version, 409, 'This setup changed. Reload before posting.');
            $before = $batch->toArray();
            $repost = JournalEntry::where('plant_id', $plantId)->where('ref_id', $batch->id)->where('ref_module', 'opening_balance')->exists();
            $data = $this->validate($batch->toArray(), $plantId);
            // Existing history would be added to these openings by the report services.
            $hasHistory = DB::table('mm_journal_entry_lines as l')
                ->join('mm_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                ->where('l.plant_id', $plantId)->whereIn('l.account_id', collect($data['lines'])->pluck('account_id')->push($batch->clearing_account_id)->filter())
                ->whereNull('l.deleted_at')->whereNull('e.deleted_at')
                ->where(fn ($q) => $q->where('l.is_deleted', 0)->orWhereNull('l.is_deleted'))
                ->where(fn ($q) => $q->where('e.is_deleted', 0)->orWhereNull('e.is_deleted'))
                ->where('e.voucher_date', '<', $batch->cutover_date->toDateString())
                ->where(fn ($q) => $q->whereNull('e.ref_module')->orWhereNotIn('e.ref_module', ['opening_balance', 'opening_balance_reversal']))
                ->exists();
            $patronIds = collect($data['lines'])->pluck('partner_id')->filter()->unique();
            if ($patronIds->isNotEmpty()) {
                // Patron statements also read source invoices/payments that may not be posted yet.
                $hasHistory = $hasHistory || DB::table('mm_invoices')->where('plant_id', $plantId)
                    ->whereIn('partner_id', $patronIds)->where('invoice_type', 'sales')->whereNull('deleted_at')
                    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Cancelled'))
                    ->where('invoice_date', '<', $batch->cutover_date->toDateString())->exists()
                    || DB::table('mm_payments')->where('plant_id', $plantId)->whereIn('patron_id', $patronIds)->whereNull('deleted_at')
                    ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['cancelled', 'rejected', 'failed']))
                    ->where('transaction_date', '<', $batch->cutover_date->toDateString())->exists();
            }
            if ($hasHistory) {
                $this->error('lines', 'Selected ledgers already have transactions before the cutover date. Reconcile existing history before importing opening balances.');
            }
            $lines = [];
            $dr = $cr = 0;
            foreach ($data['lines'] as $line) {
                $amount = self::cents($line['amount']);
                $debit = $line['side'] === 'Dr' ? $amount : 0;
                $credit = $line['side'] === 'Cr' ? $amount : 0;
                $dr += $debit;
                $cr += $credit;
                $lines[] = [
                    'account_id' => $line['account_id'], 'partner_id' => $line['partner_id'],
                    'partner_type' => $line['partner_id'] ? 'Patron' : null,
                    'debit_amount' => self::decimal($debit), 'credit_amount' => self::decimal($credit),
                    'line_narration' => $line['reference'] ?? 'Opening balance',
                ];
            }
            if ($dr !== $cr) {
                if (!$batch->clearing_account_id) {
                    $this->error('clearing_account_id', 'Debits and credits differ. Select an opening clearing ledger or complete the trial balance.');
                }
                $lines[] = ['account_id' => $batch->clearing_account_id,
                    'debit_amount' => self::decimal(max(0, $cr - $dr)),
                    'credit_amount' => self::decimal(max(0, $dr - $cr)), 'line_narration' => 'Opening balance clearing'];
            }
            $date = $batch->cutover_date->copy()->subDay()->toDateString();
            $entry = JournalEntry::create([
                'plant_id' => $plantId, 'entity_id' => $plant->entity_id,
                'voucher_type' => 'OPENING', 'voucher_number' => "OB/{$batch->id}/{$version}",
                'ref_module' => 'opening_balance', 'ref_id' => $batch->id,
                'voucher_date' => $date, 'posting_date' => $date,
                'narration' => 'Opening balances for '.$batch->cutover_date->toDateString().'. '.$batch->notes,
                'total_debit' => self::decimal(max($dr, $cr)), 'total_credit' => self::decimal(max($dr, $cr)),
                'is_status' => 'POSTED', 'created_by' => $userId,
            ]);
            foreach ($lines as $line) {
                $entry->lines()->create($line + ['plant_id' => $plantId, 'created_by' => $userId]);
            }
            $batch->update(['status' => 'POSTED', 'journal_entry_id' => $entry->id, 'posted_at' => now(), 'updated_by' => $userId, 'version' => $version + 1]);
            $this->audit($batch, $userId, $repost ? 'REPOST' : 'POST', $before, $entry);
            return $batch->fresh();
        });
    }

    public function reverse(int $plantId, int $userId, int $version, string $reason): OpeningBalanceBatch
    {
        return DB::transaction(function () use ($plantId, $userId, $version, $reason) {
            $batch = OpeningBalanceBatch::where('plant_id', $plantId)->lockForUpdate()->firstOrFail();
            abort_if($batch->status !== 'POSTED' || $batch->version !== $version, 409, 'This setup changed. Reload before reversing.');
            $before = $batch->toArray();
            $original = JournalEntry::with('lines')->where('plant_id', $plantId)->findOrFail($batch->journal_entry_id);
            $entry = JournalEntry::create([
                'plant_id' => $plantId, 'entity_id' => $original->entity_id,
                'voucher_type' => 'OPENING', 'voucher_number' => "OBR/{$batch->id}/{$version}",
                'ref_module' => 'opening_balance_reversal', 'ref_id' => $batch->id, 'reversal_of_id' => $original->id,
                'voucher_date' => $original->voucher_date, 'posting_date' => $original->posting_date,
                'narration' => 'Opening setup correction: '.$reason,
                'total_debit' => $original->total_credit, 'total_credit' => $original->total_debit,
                'is_status' => 'POSTED', 'created_by' => $userId,
            ]);
            foreach ($original->lines as $line) {
                $entry->lines()->create([
                    'plant_id' => $plantId, 'account_id' => $line->account_id,
                    'partner_id' => $line->partner_id, 'partner_type' => $line->partner_type,
                    'debit_amount' => $line->credit_amount, 'credit_amount' => $line->debit_amount,
                    'line_narration' => 'Reversal of '.$original->voucher_number, 'created_by' => $userId,
                ]);
            }
            $batch->update(['status' => 'DRAFT', 'journal_entry_id' => null, 'posted_at' => null, 'updated_by' => $userId, 'version' => $version + 1]);
            $this->audit($batch, $userId, 'REVERSE', $before, $entry, $reason);
            return $batch->fresh();
        });
    }

    private function audit(OpeningBalanceBatch $batch, int $userId, string $action, ?array $before, ?JournalEntry $entry = null, ?string $reason = null): void
    {
        // Insert within the same transaction as the balance/journal changes.
        \App\Models\OpeningBalanceAuditLog::create([
            'plant_id' => $batch->plant_id, 'batch_id' => $batch->id, 'version' => $batch->version,
            'action' => $action, 'user_id' => $userId, 'actor_name' => auth()->user()?->name,
            'reason' => $reason, 'before_values' => $before, 'after_values' => $batch->fresh()->toArray(),
            'journal' => $entry ? $entry->load('lines')->toArray() : null, 'created_at' => now(),
        ]);
    }

    private function error(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
