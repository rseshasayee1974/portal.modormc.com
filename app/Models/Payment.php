<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\AuditFields;

class Payment extends Model
{
	protected $table = 'mm_payments';
    use HasFactory, SoftDeletes, AuditFields;

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
            if (empty($payment->reference)) {
                $payment->reference = self::generateReferenceNumber(
                    $payment->plant_id ?? session('active_plant_id', 1),
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

        return substr($y1, -2) . substr($y2, -2);
    }

    public static function generateReferenceNumber($plantId, $ledgerId, $transactionType, $transactionDate = null): string
    {
        $finYearString = self::getFinancialYearString($transactionDate);
        $ledger = Ledger::find($ledgerId);
        
        $ledgerCode = 'LEDG';
        if ($ledger) {
            if (!empty($ledger->description)) {
                $ledgerCode = $ledger->description;
            } else {
                $words = explode(' ', preg_replace('/[^A-Za-z0-9\s]/', '', $ledger->title));
                if (count($words) >= 2) {
                    $ledgerCode = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . (isset($words[2]) ? substr($words[2], 0, 1) : ''));
                } else {
                    $ledgerCode = strtoupper(substr($words[0], 0, 3));
                }
            }
        }
        
        $typeShort = ($transactionType === 'receipt') ? 'REC' : 'PAY';
        $prefix = "{$ledgerCode}/{$finYearString}/";

        $lastPayment = self::where('plant_id', $plantId)
            ->where('ledger_id', $ledgerId)
            ->where('transaction_type', $transactionType)
            ->where('reference', 'like', $prefix . '%')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPayment && preg_match('/' . preg_quote($prefix, '/') . '(\d+)/i', $lastPayment->reference, $matches)) {
            $lastNumber = (int) $matches[1];
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
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
            
            $journalEntry = \App\Models\JournalEntry::updateOrCreate(
                ['ref_module' => 'payment', 'ref_id' => $this->id, 'plant_id' => $plantId],
                [
                    'entity_id'      => $entityId,
                    'voucher_type'   => $voucherType,
                    'voucher_number' => $voucherNo,
                    'voucher_date'   => $this->transaction_date,
                    'posting_date'   => $this->transaction_date,
                    'narration'      => ucfirst($this->transaction_type) . " " . $voucherNo . ($this->patron ? " | " . $this->patron->legal_name : ""),
                    'total_debit'    => $totalAmount,
                    'total_credit'   => $totalAmount,
                    'is_status'      => 'POSTED',
                    'created_by'     => \Illuminate\Support\Facades\Auth::id() ?? 1,
                ]
            );

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
                \App\Models\JournalEntryLine::create($lineData);
            }

            return $journalEntry;
        });
    }
}
