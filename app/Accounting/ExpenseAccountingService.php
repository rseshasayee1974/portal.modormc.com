<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 10:20:39 Asia/Calcutta (UTC+05:30)
*/

namespace App\Accounting;

use App\Models\{Expense, ExpenseType, JournalEntry, Ledger, Plant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseAccountingService
{
    /** Called inside the expense save transaction so both records commit together. */
    public function post(Expense $expense, string $errorPrefix = ''): JournalEntry
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Expense posting requires a transaction.');
        }
        $fail = function (string $field, string $message) use ($errorPrefix): never {
            throw ValidationException::withMessages([$errorPrefix . $field => $message]);
        };
        $category = ExpenseType::where('plant_id', $expense->plant_id)
            ->lockForUpdate()->find($expense->expense_type_id);
        if (!$category || !$category->status || !$category->ledger_id) {
            $fail('expense_type_id', 'Select an active expense category with an expense ledger configured.');
        }
        $ledgers = Ledger::where('plant_id', $expense->plant_id)->where('status', 1)
            ->whereIn('id', [$category->ledger_id, $expense->paid_through])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if (!$ledgers->has($category->ledger_id)) {
            $fail('expense_type_id', 'The category expense ledger must be active and belong to this plant.');
        }
        if (!$ledgers->has($expense->paid_through)) {
            $fail('paid_through', 'Select an active payment ledger belonging to this plant.');
        }
        if ((int) $category->ledger_id === (int) $expense->paid_through) {
            $fail('paid_through', 'Payment ledger and expense ledger must be different.');
        }
        if ((float) $expense->amount <= 0 || !$expense->date) {
            $fail('amount', 'A positive expense amount and expense date are required for accounting.');
        }
        if (mb_strlen((string) $expense->ref_no) > 50) {
            $fail('ref_no', 'The reference number must not exceed 50 characters for accounting.');
        }

        $entry = JournalEntry::withTrashed()->where('plant_id', $expense->plant_id)
            ->where('ref_module', 'expense')->where('ref_id', $expense->id)->lockForUpdate()->first();
        $collision = JournalEntry::withTrashed()->where('plant_id', $expense->plant_id)
            ->where('voucher_type', 'JOURNAL')->where('voucher_number', $expense->ref_no)
            ->when($entry, fn ($query) => $query->whereKeyNot($entry->id))->exists();
        if ($collision) {
            $fail('ref_no', 'This reference is already used by another journal. Enter a different reference number.');
        }
        if ($entry?->trashed()) {
            $entry->restore();
        }
        $entry ??= new JournalEntry();
        $entry->fill([
            'plant_id' => $expense->plant_id,
            'entity_id' => Plant::findOrFail($expense->plant_id)->entity_id,
            'ref_module' => 'expense', 'ref_id' => $expense->id,
            'voucher_type' => 'JOURNAL', 'voucher_number' => $expense->ref_no,
            'voucher_date' => $expense->date, 'posting_date' => $expense->date,
            'narration' => trim('Expense: ' . $expense->ref_no . ' | ' . $category->name . ' | ' . $expense->note),
            'narration_label' => 'Expense', 'is_status' => 'POSTED',
            'total_debit' => $expense->amount, 'total_credit' => $expense->amount,
        ])->save();
        // Preserve old lines as audit history while replacing their active version.
        $entry->lines()->get()->each->delete();
        foreach ([[$category->ledger_id, $expense->amount, 0, 'Expense'],
            [$expense->paid_through, 0, $expense->amount, 'Payment']] as [$ledgerId, $debit, $credit, $label]) {
            $entry->lines()->create([
                'plant_id' => $expense->plant_id, 'account_id' => $ledgerId,
                'debit_amount' => $debit, 'credit_amount' => $credit,
                'narration_name' => $label, 'narration_label' => 'Expense',
                'line_narration' => $expense->note ?: 'Expense ' . $expense->ref_no,
            ]);
        }
        return $entry;
    }

    public function remove(Expense $expense): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Expense accounting deletion requires a transaction.');
        }
        JournalEntry::where('plant_id', $expense->plant_id)->where('ref_module', 'expense')
            ->where('ref_id', $expense->id)->lockForUpdate()->get()->each->delete();
    }
}
