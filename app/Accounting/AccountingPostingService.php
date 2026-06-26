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
 * Caller is responsible for wrapping in DB::transaction().
 * This service does NOT open its own transaction — it must run inside the
 * same transaction as the document creation so a posting failure rolls
 * back the entire invoice creation.
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
        $docType = strtolower($document->getDocumentType());
        $config  = DocumentTypeConfig::get($docType); // throws if unsupported type

        // Build lines — throws AccountingException if ledgers missing or imbalanced
        $builder = new JournalEntryBuilder($document, $config, $this->ledgerResolver);
        $lines   = $builder->build();

        $plantId  = $document->getPlantId();
        $userId   = Auth::id() ?? 1;

        // Upsert journal entry header — idempotent repost supported
        $journalEntry = JournalEntry::updateOrCreate(
            [
                'ref_module' => $docType,
                'ref_id'     => $document->getDocumentId(),
                'plant_id'   => $plantId,
            ],
            [
                'entity_id'      => $document->getEntityId(),
                'voucher_type'   => $config['voucher_type'],
                'voucher_number' => $document->getVoucherNumber(),
                'voucher_date'   => $document->getVoucherDate(),
                'posting_date'   => $document->getVoucherDate(),
                'narration'      => ucfirst($docType) . ': '
                                    . $document->getVoucherNumber()
                                    . ' | ' . $document->getPartnerName(),
                'total_debit'    => 0,  // finalized below
                'total_credit'   => 0,
                'is_status'      => 'POSTED',
                'created_by'     => $userId,
            ]
        );

        // Wipe old lines — within the same transaction, so safe
        $journalEntry->lines()->delete();

        $totalDebitCents  = 0;
        $totalCreditCents = 0;

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