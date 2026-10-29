<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Contracts\Postable;
use App\Exceptions\AccountingException;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full posting flow.
 *
 * Opens a transaction and serialises posting on the plant row. When called
 * inside document creation it joins that transaction, so a posting failure
 * rolls back the entire document as well as its journal.
 *
 * Usage:
 *   DB::transaction(function() {
 *       $invoice = Invoice::createWithItems($data);
 *       app(AccountingPostingService::class)->post($invoice);
 *   });
 */
class AccountingPostingService
{
    public function __construct(
        private readonly LedgerResolver $ledgerResolver,
    ) {}

    /**
     * Post a Postable document to the journal.
     *
     * @throws AccountingException  on config/data problems (user-fixable)
     * @throws \Throwable           on unexpected failures (bug — let it propagate)
     */
    public function post(Postable $document): JournalEntry
    {
        return DB::transaction(function () use ($document) {
            $plant = DB::table('mm_plants')->where('id', $document->getPlantId())
                ->whereNull('deleted_at')->lockForUpdate()->first();
            if (!$plant) {
                throw new AccountingException('Cannot post without an active plant.');
            }
            return $this->postLocked($document);
        }, 5);
    }

    private function postLocked(Postable $document): JournalEntry
    {
        $docType = strtolower($document->getDocumentType());
        $config  = DocumentTypeConfig::get($docType); // throws if unsupported type

        // Build lines — throws AccountingException if ledgers missing or imbalanced
        $builder = new JournalEntryBuilder($document, $config, $this->ledgerResolver);
        $lines   = $builder->build();

        $ids = array_values(array_unique(array_column($lines, 'account_id')));
        $owned = DB::table('mm_ledgers')->where('plant_id', $document->getPlantId())
            ->whereNull('deleted_at')->whereIn('id', $ids)->count();
        if ($owned !== count($ids)) {
            throw new AccountingException('Every posting ledger must belong to the document plant.');
        }
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debit = $line['debit_cents'];
            $credit = $line['credit_cents'];
            if (!is_int($debit) || !is_int($credit) || $debit < 0 || $credit < 0
                || (($debit > 0) === ($credit > 0))) {
                throw new AccountingException('Journal lines must contain exactly one positive integer-paise side.');
            }
            $debits += $debit;
            $credits += $credit;
        }
        if (count($lines) < 2 || $debits !== $credits || $debits === 0) {
            throw new AccountingException('Posting requires at least two balanced, nonzero journal lines.');
        }

        $plantId  = $document->getPlantId();
        $userId   = Auth::id() ?? 1;

        if (\Illuminate\Support\Facades\Schema::hasTable('mm_accounting_journals')) {
            $userId = Auth::id() ?? ($document instanceof \Illuminate\Database\Eloquent\Model ? ($document->getAttribute('updated_by') ?: $document->getAttribute('created_by')) : null);
            if (! $userId) throw new AccountingException('A posting actor is required; background jobs must retain the document creator.');
            $money = fn (int $n) => intdiv($n, 100).'.'.str_pad((string) ($n % 100), 2, '0', STR_PAD_LEFT);
            $entry = app(\App\Services\Finance\SourceJournalService::class)->post($plantId, (int) $userId, [
                'voucher_type' => $config['voucher_type'], 'voucher_number' => $document->getVoucherNumber(),
                'ref_module' => $docType, 'ref_id' => $document->getDocumentId(),
                'voucher_date' => $document->getVoucherDate()->format('Y-m-d'), 'posting_date' => $document->getVoucherDate()->format('Y-m-d'),
                'narration' => ucfirst($docType).': '.$document->getVoucherNumber().' | '.$document->getPartnerName(),
                'narration_label' => JournalEntry::resolveNarrationLabel($docType, $config['voucher_type']),
            ], array_map(fn ($l) => ['account_id' => $l['account_id'], 'debit_amount' => $money($l['debit_cents']), 'credit_amount' => $money($l['credit_cents']),
                'partner_id' => $l['partner_id'] ?? null, 'partner_type' => isset($l['partner_id']) ? 'Patron' : null, 'tax_id' => $l['tax_id'] ?? null,
                'narration_name' => $l['narration_name'], 'line_narration' => $l['line_narration']], $lines));
            return JournalEntry::findOrFail($entry->id);
        }

        $existing = JournalEntry::where('plant_id', $plantId)->where('voucher_type', $config['voucher_type'])
            ->where('voucher_number', $document->getVoucherNumber())->lockForUpdate()->first();
        if ($existing) {
            $oldModule = match (strtolower((string) $existing->ref_module)) { 'sales' => 'invoice', 'purchase' => 'bill', default => strtolower((string) $existing->ref_module) };
            if ((int) $existing->ref_id !== $document->getDocumentId() || $oldModule !== $docType) {
                throw new AccountingException('Voucher number already belongs to another source document.');
            }
        }

        // Upsert journal entry header — idempotent repost supported
        $journalEntry = JournalEntry::updateOrCreate(
            [
                'plant_id'       => $plantId,
                'voucher_type'   => $config['voucher_type'],
                'voucher_number' => $document->getVoucherNumber(),
            ],
            [
                'ref_module'      => $docType,
                'ref_id'          => $document->getDocumentId(),
                'entity_id'       => $document->getEntityId(),
                'voucher_date'    => $document->getVoucherDate(),
                'posting_date'    => $document->getVoucherDate(),
                'narration'       => ucfirst($docType) . ': '
                                    . $document->getVoucherNumber()
                                    . ' | ' . $document->getPartnerName(),
                'narration_label' => JournalEntry::resolveNarrationLabel($docType, $config['voucher_type']),
                'total_debit'     => 0,  // finalized below
                'total_credit'    => 0,
                'is_status'       => 'POSTED',
                'created_by'      => $userId,
            ]
        );

        // Wipe old lines — within the same transaction, so safe
        $journalEntry->lines()->delete();

        $totalDebitCents  = 0;
        $totalCreditCents = 0;

        $narrationLabel = $journalEntry->narration_label ?? JournalEntry::resolveNarrationLabel($docType, $config['voucher_type']);

        foreach ($lines as $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'plant_id'         => $plantId,
                'account_id'       => $line['account_id'],
                'debit_amount'     => $line['debit_cents'] / 100,   // store as decimal(15,2)
                'credit_amount'    => $line['credit_cents'] / 100,
                'partner_type'     => isset($line['partner_id']) ? 'Patron' : null,
                'partner_id'       => $line['partner_id'] ?? null,
                'tax_id'           => $line['tax_id'] ?? null,
                'narration_name'   => $line['narration_name'],
                'narration_label'  => $narrationLabel,
                'line_narration'   => $line['line_narration'],
                'created_by'       => $userId,
            ]);

            $totalDebitCents  += $line['debit_cents'];
            $totalCreditCents += $line['credit_cents'];
        }

        // Finalize header
        $journalEntry->update([
            'total_debit'  => $totalDebitCents / 100,
            'total_credit' => $totalCreditCents / 100,
            'is_status'    => 'POSTED',
        ]);

        Log::info("Accounting posted: {$docType} #{$document->getVoucherNumber()}", [
            'journal_entry_id' => $journalEntry->id,
            'document_id'      => $document->getDocumentId(),
            'total'            => $totalDebitCents / 100,
            'lines_count'      => count($lines),
        ]);

        return $journalEntry;
    }
}