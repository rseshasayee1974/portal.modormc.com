<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TracksModelChanges;
class Payment extends Model
{
    use TracksModelChanges;

	protected $table = 'mm_payments';
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'plant_id',
        'origin',
        'origin_id',
        'transaction_date',
        'ledger_id',
        'patron_id',
        'partner_type',
        'amount',
        'excess_amount',
        'use_excess_amount',
        'transaction_type',
        'transaction_mode',
        'reconcile_opening_balance',
        'batch_deposit',
        'description',
        'reference',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'excess_amount' => 'decimal:2',
        'use_excess_amount' => 'boolean',
        'reconcile_opening_balance' => 'boolean',
        'batch_deposit' => 'boolean',
        'transaction_date' => 'date:Y-m-d'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $plantId = $payment->plant_id ?? session('active_plant_id', 1);
            if (empty($payment->reference) || self::where('plant_id', $plantId)->where('reference', $payment->reference)->exists()) {
                $payment->reference = self::generateReferenceNumber(
                    $plantId,
                    $payment->ledger_id,
                    $payment->transaction_type,
                    $payment->transaction_date
                );
            }
        });
    }

    public static function getFinancialYearString($date = null): string
    {
        $timestamp = $date ? strtotime($date) : time();
        $currentMonth = (int) date('m', $timestamp);
        $currentYear = (int) date('Y', $timestamp);

        if ($currentMonth < 4) {
            $y1 = $currentYear - 1;
            $y2 = $currentYear;
        } else {
            $y1 = $currentYear;
            $y2 = $currentYear + 1;
        }

        return substr($y1, -2) .'-'. substr($y2, -2);
    }

    public static function generateReferenceNumber($plantId, $ledgerId, $transactionType, $transactionDate = null): string
    {
        $isReceipt = strtolower((string)$transactionType) === 'receipt';
        $typeShort = $isReceipt ? 'REC' : 'PAY';
        $voucherType = $isReceipt ? 'RECEIPT' : 'PAYMENT';
        $finYearString = self::getFinancialYearString($transactionDate);
        $ledger = Ledger::find($ledgerId);
        
        $prefix = '';
        if ($ledger && !empty(trim((string)$ledger->description))) {
            $desc = trim((string)$ledger->description);
            
            // 1. If description contains template tags like {type} or {fy}
            if (stripos($desc, '{type}') !== false || stripos($desc, '{fy}') !== false) {
                $prefix = str_ireplace(['{type}', '{fy}'], [$typeShort, $finYearString], $desc);
            }
            // 2. If description already explicitly contains the type (PAY or REC)
            elseif (stripos($desc, $typeShort) !== false) {
                if (str_ends_with($desc, '-')) {
                    $prefix = $desc;
                } elseif (str_ends_with($desc, '/')) {
                    $prefix = preg_match('/\d{4}/', $desc) ? $desc : rtrim($desc, '/') . "/{$finYearString}/";
                } else {
                    $prefix = "{$desc}/{$finYearString}/";
                }
            }
            // 3. If description is hyphenated (e.g. 'PC-')
            elseif (str_ends_with($desc, '-')) {
                $cleanDesc = rtrim($desc, '-');
                $prefix = "{$cleanDesc}-{$typeShort}-";
            }
            // 4. Standard ledger code (e.g. 'SBI', 'CASH', 'HDFC/')
            else {
                $cleanDesc = rtrim($desc, '/');
                $prefix = "{$cleanDesc}/{$typeShort}/{$finYearString}/";
            }
        } else {
            // Default fallback when ledger description is empty:
            // Separate Payment (PAY/26-27/...) and Receipt (REC/26-27/...)
            $prefix = "{$typeShort}/{$finYearString}/";
        }

        // Gather all active references from payments
        $paymentRefs = self::where('plant_id', $plantId)
            ->where('reference', 'like', $prefix . '%')
            ->pluck('reference');

        // Gather all active voucher numbers from journal entries
        $journalVouchers = \App\Models\JournalEntry::where('plant_id', $plantId)
            ->where('voucher_type', $voucherType)
            ->where('voucher_number', 'like', $prefix . '%')
            ->where('is_deleted', 0)
            ->whereNull('deleted_at')
            ->pluck('voucher_number');

        $usedNumbers = [];
        $pattern = '/' . preg_quote($prefix, '/') . '(\d+)/i';

        foreach ($paymentRefs as $ref) {
            if (preg_match($pattern, $ref, $matches)) {
                $usedNumbers[(int) $matches[1]] = true;
            }
        }

        foreach ($journalVouchers as $vNum) {
            if (str_contains($vNum, '_DEL_')) {
                continue;
            }
            if (preg_match($pattern, $vNum, $matches)) {
                $usedNumbers[(int) $matches[1]] = true;
            }
        }

        $nextNumber = 1;
        while (isset($usedNumbers[$nextNumber])) {
            $nextNumber++;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function patron()
    {
        return $this->belongsTo(Patron::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Complete the payment transaction. State transition.
     */
    public function transitionToCompleted(): void
    {
        if ($this->status !== 'completed') {
            $this->status = 'completed';
            $this->save();
        }
    }

    public function transitionToFailed(): void
    {
        if ($this->status !== 'failed') {
            $this->status = 'failed';
            $this->save();
        }
    }

    /**
     * Post the payment/receipt to the main Journal Entry Accounting system.
     */
    public function postToAccounting(): \App\Models\JournalEntry
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $this->refresh();
            $plantId = $this->plant_id ?? session('active_plant_id', 1);
            $entityId = $this->plant->entity_id ?? session('active_entity_id', 1);
            $totalAmount = round((float)($this->amount ?? 0), 2);
            
            $voucherType = $this->transaction_type === 'receipt' ? 'RECEIPT' : 'PAYMENT';
            $voucherNo = $this->reference ?? strtoupper(substr($this->transaction_type, 0, 3)) . '-' . $this->id;

            // Resolve any conflicting voucher in mm_journal_entries to avoid 1062 duplicate key error on uk_voucher
            $conflictingEntry = \App\Models\JournalEntry::withTrashed()
                ->where('plant_id', $plantId)
                ->where('voucher_type', $voucherType)
                ->where('voucher_number', $voucherNo)
                ->first();

            if ($conflictingEntry) {
                $isOwnEntry = ($conflictingEntry->ref_module === 'payment' && (int)$conflictingEntry->ref_id === (int)$this->id);

                if (!$isOwnEntry) {
                    $isSoftDeleted = $conflictingEntry->trashed() || (bool)$conflictingEntry->is_deleted;
                    if (!$isSoftDeleted && $conflictingEntry->ref_module === 'payment') {
                        $linkedPayment = self::withTrashed()->find($conflictingEntry->ref_id);
                        if (!$linkedPayment || $linkedPayment->trashed()) {
                            $isSoftDeleted = true;
                        }
                    }

                    if ($isSoftDeleted) {
                        // Free up the voucher number by renaming the soft-deleted entry
                        $renamed = substr($conflictingEntry->voucher_number, 0, 35) . '_DEL_' . $conflictingEntry->id;
                        \Illuminate\Support\Facades\DB::table('mm_journal_entries')
                            ->where('id', $conflictingEntry->id)
                            ->update([
                                'voucher_number' => $renamed,
                                'is_deleted'     => 1,
                                'deleted_at'     => $conflictingEntry->deleted_at ?? now(),
                            ]);
                    } else {
                        // Number is genuinely taken by another active transaction; generate next sequence number
                        $voucherNo = self::generateReferenceNumber(
                            $plantId,
                            $this->ledger_id,
                            $this->transaction_type,
                            $this->transaction_date
                        );
                        $this->reference = $voucherNo;
                        $this->saveQuietly();

                        PaymentTransaction::where('payment_id', $this->id)
                            ->update(['reference' => $voucherNo]);
                    }
                }
            }

            try {
                $journalEntry = \App\Models\JournalEntry::updateOrCreate(
                    ['ref_module' => 'payment', 'ref_id' => $this->id, 'plant_id' => $plantId],
                    [
                        'entity_id'      => $entityId,
                        'voucher_type'   => $voucherType,
                        'voucher_number' => $voucherNo,
                        'voucher_date'   => $this->transaction_date,
                        'posting_date'   => $this->transaction_date,
                        'narration'       => ucfirst($this->transaction_type) . " " . $voucherNo . ($this->patron ? " | " . $this->patron->legal_name : ""),
                        'narration_label' => $this->transaction_type === 'receipt' ? 'Receipt' : 'Payment',
                        'total_debit'     => $totalAmount,
                        'total_credit'    => $totalAmount,
                        'is_status'       => 'POSTED',
                        'created_by'      => \Illuminate\Support\Facades\Auth::id() ?? 1,
                    ]
                );
            } catch (\Illuminate\Database\QueryException $qe) {
                if ($qe->errorInfo[1] == 1062 || str_contains($qe->getMessage(), '1062 Duplicate entry')) {
                    // In case of a race condition, advance reference number and retry
                    $voucherNo = self::generateReferenceNumber(
                        $plantId,
                        $this->ledger_id,
                        $this->transaction_type,
                        $this->transaction_date
                    );
                    $this->reference = $voucherNo;
                    $this->saveQuietly();

                    PaymentTransaction::where('payment_id', $this->id)
                        ->update(['reference' => $voucherNo]);

                    $journalEntry = \App\Models\JournalEntry::updateOrCreate(
                        ['ref_module' => 'payment', 'ref_id' => $this->id, 'plant_id' => $plantId],
                        [
                            'entity_id'      => $entityId,
                            'voucher_type'   => $voucherType,
                            'voucher_number' => $voucherNo,
                            'voucher_date'   => $this->transaction_date,
                            'posting_date'   => $this->transaction_date,
                            'narration'       => ucfirst($this->transaction_type) . " " . $voucherNo . ($this->patron ? " | " . $this->patron->legal_name : ""),
                            'narration_label' => $this->transaction_type === 'receipt' ? 'Receipt' : 'Payment',
                            'total_debit'     => $totalAmount,
                            'total_credit'    => $totalAmount,
                            'is_status'       => 'POSTED',
                            'created_by'      => \Illuminate\Support\Facades\Auth::id() ?? 1,
                        ]
                    );
                } else {
                    throw $qe;
                }
            }

            // Clear existing lines to rebuild
            $journalEntry->lines()->delete();

            $lines = [];
            $partyLedgerId = $this->patron?->ledger_id;

            if (!$partyLedgerId) {
                // Fallback to default Sundry ledger if specific one isn't set
                $fallbackSearch = $this->transaction_type === 'receipt' ? 'Sundry Debtor' : 'Sundry Creditor';
                $partyLedgerId = \App\Models\Ledger::where('title', 'like', "%{$fallbackSearch}%")
                    ->where('plant_id', $plantId)
                    ->value('id');
            }

            if (!$partyLedgerId) {
                throw new \Exception("Accounting Failure: Partner (Patron) does not have an associated Ledger, and no default '{$fallbackSearch}' ledger was found. Please map the partner to a ledger.");
            }

            if ($this->transaction_type === 'receipt') {
                // Receipt: Debit Bank/Cash, Credit Party
                $lines[] = [
                    'account_id'     => $this->ledger_id,
                    'debit_amount'   => $totalAmount,
                    'credit_amount'  => 0,
                    'narration_name' => 'Bank/Cash',
                    'line_narration' => "Receipt #{$voucherNo}",
                ];
                $lines[] = [
                    'account_id'     => $partyLedgerId,
                    'debit_amount'   => 0,
                    'credit_amount'  => $totalAmount,
                    'partner_type'   => 'Patron',
                    'partner_id'     => $this->patron_id,
                    'narration_name' => 'Party',
                    'line_narration' => "Receipt #{$voucherNo}",
                ];
            } else {
                // Payment: Debit Party, Credit Bank/Cash
                $lines[] = [
                    'account_id'     => $partyLedgerId,
                    'debit_amount'   => $totalAmount,
                    'credit_amount'  => 0,
                    'partner_type'   => 'Patron',
                    'partner_id'     => $this->patron_id,
                    'narration_name' => 'Party',
                    'line_narration' => "Payment #{$voucherNo}",
                ];
                $lines[] = [
                    'account_id'     => $this->ledger_id,
                    'debit_amount'   => 0,
                    'credit_amount'  => $totalAmount,
                    'narration_name' => 'Bank/Cash',
                    'line_narration' => "Payment #{$voucherNo}",
                ];
            }

            foreach ($lines as $lineData) {
                $lineData['journal_entry_id'] = $journalEntry->id;
                $lineData['plant_id']         = $plantId;
                $lineData['created_by']       = \Illuminate\Support\Facades\Auth::id() ?? 1;
                $lineData['narration_label']  = $journalEntry->narration_label;
                \App\Models\JournalEntryLine::create($lineData);
            }

            return $journalEntry;
        });
    }
}