<?php

namespace App\Models;

use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountDiscount extends Model
{
    use PlantScoping, TracksModelChanges , SoftDeletes;

    protected $table = 'mm_account_discount';
    public const UPDATED_AT = 'modified_at';
    protected $fillable = [
        'primary_type', 'value_type', 'value', 'amount', 'journal_id', 'account_id',
        'partner_id', 'invoice_id', 'billing_id', 'payment_id', 'reference_number', 'move_id', 'date', 'note', 'status',
    ];
    protected $casts = [
        'value' => 'decimal:2',
        'amount' => 'decimal:2',
        'date' => 'date:Y-m-d',
        'status' => 'integer',
        'invoice_id' => 'integer',
        'billing_id' => 'integer',
        'payment_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AccountDiscount $model) {
            if (empty($model->reference_number)) {
                $prefix = $model->primary_type === 'Purchase' ? 'PDISC' : 'SDISC';
                $now = now();
                $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
                $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
                $plantPrefix = "{$prefix}/{$fyString}/";

                // Retrieve reference numbers of active (non-deleted) discounts for this plant & prefix
                $activeRefs = self::where('plant_id', $model->plant_id)
                    ->where('reference_number', 'LIKE', "{$plantPrefix}%")
                    ->pluck('reference_number');

                $usedSequences = [];
                foreach ($activeRefs as $ref) {
                    if (preg_match('/\/(\d+)$/', $ref, $matches)) {
                        $usedSequences[(int) $matches[1]] = true;
                    }
                }

                // Retrieve reference numbers of soft-deleted discounts, ordered by latest deleted first
                $trashedRefs = self::onlyTrashed()
                    ->where('plant_id', $model->plant_id)
                    ->where('reference_number', 'LIKE', "{$plantPrefix}%")
                    ->orderByDesc('deleted_at')
                    ->orderByDesc('id')
                    ->pluck('reference_number');

                $sequence = null;
                foreach ($trashedRefs as $ref) {
                    if (preg_match('/\/(\d+)$/', $ref, $matches)) {
                        $seq = (int) $matches[1];
                        if (!isset($usedSequences[$seq])) {
                            $sequence = $seq;
                            break;
                        }
                    }
                }

                // If no unused soft-deleted sequence exists, take the next sequence after the maximum active sequence
                if ($sequence === null) {
                    $sequence = !empty($usedSequences) ? (max(array_keys($usedSequences)) + 1) : 1;
                }

                $model->reference_number = sprintf('%s%05d', $plantPrefix, $sequence);
            }
        });

        static::saving(function (AccountDiscount $model) {
            if ($model->journal_id) {
                if (empty($model->move_id)) {
                    $model->move_id = $model->journal_id;
                }
                if (empty($model->invoice_id) && empty($model->billing_id) && empty($model->payment_id)) {
                    $journal = \Illuminate\Support\Facades\DB::table('mm_journal_entries')
                        ->where('id', $model->journal_id)
                        ->first(['ref_module', 'ref_id', 'voucher_type']);
                    if ($journal) {
                        $vType = strtoupper($journal->voucher_type ?? '');
                        $refMod = strtolower($journal->ref_module ?? '');
                        $refId = property_exists($journal, 'ref_id') ? $journal->ref_id : null;
                        $hasInvoices = \Illuminate\Support\Facades\Schema::hasTable('mm_invoices');
                        if ($model->primary_type === 'Sales' || $vType === 'SALES' || $refMod === 'invoice') {
                            $model->invoice_id = $refId ?: ($hasInvoices ? \Illuminate\Support\Facades\DB::table('mm_invoices')->where('journal_id', $model->journal_id)->value('id') : null);
                        } elseif ($model->primary_type === 'Purchase' || $vType === 'PURCHASE' || $refMod === 'bill') {
                            $model->billing_id = $refId ?: ($hasInvoices ? \Illuminate\Support\Facades\DB::table('mm_invoices')->where('journal_id', $model->journal_id)->value('id') : null);
                        } elseif (in_array($vType, ['PAYMENT', 'RECEIPT']) || $refMod === 'payment') {
                            $model->payment_id = $refId;
                        }
                    }
                }
            }
        });

        static::deleted(function (AccountDiscount $model) {
            JournalEntry::where('ref_module', 'discount')
                ->where('ref_id', $model->id)
                ->get()
                ->each(function (JournalEntry $entry) {
                    $entry->delete();
                });
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'billing_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'account_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Patron::class, 'partner_id')->withoutGlobalScope('active_operational_status');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    /**
     * Post this discount to mm_journal_entries and mm_journal_entry_lines.
     */
    public function postToAccounting(): ?JournalEntry
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $this->refresh();
            $plantId = (int) ($this->plant_id ?? session('active_plant_id'));
            $entityId = $this->plant?->entity_id ?? session('active_entity_id');
            $amount = (float) ($this->amount ?? 0);

            if ($amount <= 0) {
                return null;
            }

            $voucherType = 'Discount Accounting';
            $voucherNo = $this->reference_number ?: ('DISC-' . $this->id);
            $isSales = $this->primary_type === 'Sales';

            // 1. Resolve Party Ledger
            $partyLedgerId = $isSales ? $this->partner?->debit_ledger_id : $this->partner?->credit_ledger_id;
            if (!$partyLedgerId) {
                $partySettingKey = $isSales ? 'debit_ledger' : 'credit_ledger';
                $partyLedgerId = AccountDefaultSetting::where('plant_id', $plantId)
                    ->where('module_name', 'Patron')
                    ->where('setting_key', $partySettingKey)
                    ->where('is_active', true)
                    ->value('ledger_id');
            }
            if (!$partyLedgerId) {
                $search = $isSales ? 'Sundry Debtor' : 'Sundry Creditor';
                $partyLedgerId = Ledger::where('plant_id', $plantId)
                    ->where('title', 'like', "%{$search}%")
                    ->value('id');
            }

            // 2. Resolve Discount Ledger (Expense for Sales, Income for Purchase)
            $discountLedgerId = $this->account_id;
            if (!$discountLedgerId) {
                $discSettingKey = $isSales ? 'discount_allowed' : 'discount_received';
                $discountLedgerId = AccountDefaultSetting::where('plant_id', $plantId)
                    ->where('setting_key', $discSettingKey)
                    ->where('is_active', true)
                    ->value('ledger_id');
            }
            if (!$discountLedgerId) {
                $search = $isSales ? 'Discount Allowed' : 'Discount Received';
                $discountLedgerId = Ledger::where('plant_id', $plantId)
                    ->where('title', 'like', "%{$search}%")
                    ->value('id');
            }
            if (!$discountLedgerId) {
                $discountLedgerId = Ledger::where('plant_id', $plantId)
                    ->where('title', 'like', '%Discount%')
                    ->value('id');
            }

            if (!$partyLedgerId || !$discountLedgerId) {
                \Illuminate\Support\Facades\Log::warning("AccountDiscount #{$this->id}: Missing ledger mapping for party ({$partyLedgerId}) or discount ({$discountLedgerId}).");
                return null;
            }

            $partnerName = $this->partner?->legal_name ?? 'Unknown Partner';
            $voucherDate = $this->date ? $this->date->format('Y-m-d') : now()->toDateString();
            $narration = "{$this->primary_type} Discount #{$voucherNo} | {$partnerName}" . ($this->note ? " - {$this->note}" : "");
            $narrationLabel = "{$this->primary_type} Discount";

            $journalEntry = JournalEntry::updateOrCreate(
                [
                    'ref_module' => 'discount',
                    'ref_id'     => $this->id,
                    'plant_id'   => $plantId,
                ],
                [
                    'entity_id'       => $entityId,
                    'voucher_type'    => $voucherType,
                    'voucher_number'  => $voucherNo,
                    'voucher_date'    => $voucherDate,
                    'posting_date'    => $voucherDate,
                    'narration'       => $narration,
                    'narration_label' => $narrationLabel,
                    'total_debit'     => $amount,
                    'total_credit'    => $amount,
                    'is_status'       => 'POSTED',
                    'created_by'      => \Illuminate\Support\Facades\Auth::id() ?? $this->created_by ?? 1,
                ]
            );

            // Rebuild lines
            $journalEntry->lines()->delete();

            $lines = [];
            if ($isSales) {
                // Sales Discount:
                // Debit: Discount Account (Expense)
                // Credit: Customer (Receivable decreased)
                $lines[] = [
                    'journal_entry_id' => $journalEntry->id,
                    'plant_id'         => $plantId,
                    'account_id'       => $discountLedgerId,
                    'debit_amount'     => $amount,
                    'credit_amount'    => 0,
                    'narration_name'   => 'Discount Allowed',
                    'narration_label'  => $narrationLabel,
                    'line_narration'   => "Discount for Sales #{$voucherNo}",
                    'created_by'       => \Illuminate\Support\Facades\Auth::id() ?? $this->created_by ?? 1,
                ];
                $lines[] = [
                    'journal_entry_id' => $journalEntry->id,
                    'plant_id'         => $plantId,
                    'account_id'       => $partyLedgerId,
                    'debit_amount'     => 0,
                    'credit_amount'    => $amount,
                    'partner_type'     => 'Patron',
                    'partner_id'       => $this->partner_id,
                    'narration_name'   => 'Customer Account',
                    'narration_label'  => $narrationLabel,
                    'line_narration'   => "Discount for Sales #{$voucherNo}",
                    'created_by'       => \Illuminate\Support\Facades\Auth::id() ?? $this->created_by ?? 1,
                ];
            } else {
                // Purchase Discount:
                // Debit: Supplier (Payable decreased)
                // Credit: Discount Account (Income)
                $lines[] = [
                    'journal_entry_id' => $journalEntry->id,
                    'plant_id'         => $plantId,
                    'account_id'       => $partyLedgerId,
                    'debit_amount'     => $amount,
                    'credit_amount'    => 0,
                    'partner_type'     => 'Patron',
                    'partner_id'       => $this->partner_id,
                    'narration_name'   => 'Supplier Account',
                    'narration_label'  => $narrationLabel,
                    'line_narration'   => "Discount for Purchases #{$voucherNo}",
                    'created_by'       => \Illuminate\Support\Facades\Auth::id() ?? $this->created_by ?? 1,
                ];
                $lines[] = [
                    'journal_entry_id' => $journalEntry->id,
                    'plant_id'         => $plantId,
                    'account_id'       => $discountLedgerId,
                    'debit_amount'     => 0,
                    'credit_amount'    => $amount,
                    'narration_name'   => 'Discount Received',
                    'narration_label'  => $narrationLabel,
                    'line_narration'   => "Discount for Purchases #{$voucherNo}",
                    'created_by'       => \Illuminate\Support\Facades\Auth::id() ?? $this->created_by ?? 1,
                ];
            }

            foreach ($lines as $line) {
                JournalEntryLine::create($line);
            }

            // Sync move_id to the created journal entry id
            $this->move_id = $journalEntry->id;
            $this->saveQuietly();

            return $journalEntry;
        });
    }
}
