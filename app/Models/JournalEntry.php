<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TracksModelChanges;
use App\Models\VoucherType;
use App\Models\Ledger;
use App\Models\Entity;
use App\Models\Patron;
use App\Models\AccountDefaultSetting;

class JournalEntry extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_journal_entries';

    protected $fillable = [
        'entity_id',
        'plant_id',
        'voucher_type',
        'voucher_number',
        'ref_module',
        'ref_name',
        'ref_id',
        'voucher_date',
        'posting_date',
        'narration',
        'narration_label',
        'total_debit',
        'total_credit',
        'is_status',
        'reversal_of_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'voucher_date' => 'date:Y-m-d',
        'posting_date' => 'date:Y-m-d',
        'total_debit'  => 'decimal:4',
        'total_credit' => 'decimal:4',
        'is_deleted'   => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($entry) {
            // Mass update lines to ensure is_deleted, deleted_by, and deleted_at are set
            $entry->lines()->update([
                'is_deleted' => 1,
                'deleted_by' => auth()->id(),
                'deleted_at' => now(),
            ]);

            $entry->updateQuietly([
                'is_deleted' => 1,
                'deleted_by' => auth()->id(),
                'deleted_at' => now(),
            ]);
        });
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBalanced()
    {
        return $this->total_debit === $this->total_credit;
    }

    public static function resolveNarrationLabel(?string $refModule, ?string $voucherType = null): string
    {
        $module = strtolower($refModule ?? '');
        $vType = strtoupper($voucherType ?? '');

        return match ($module) {
            'invoice', 'sales'           => 'Sales',
            'purchase', 'purchase_order' => 'Purchase',
            'bill'                       => 'Purchase Bill',
            'payment'                    => $vType === 'RECEIPT' ? 'Receipt' : 'Payment',
            'expense'                    => 'Expense',
            'dispatch'                   => 'Dispatch',
            'stockin'                    => 'StockIn',
            'stockout'                   => 'StockOut',
            'bank_reconciliation', 'brs' => 'Bank Reconciliation',
            default                      => match ($vType) {
                'SALES'         => 'Sales',
                'PURCHASE'      => 'Purchase',
                'PAYMENT'       => 'Payment',
                'RECEIPT'       => 'Receipt',
                'JOURNAL', 'JV' => 'Manual Journal',
                default         => !empty($refModule) ? ucfirst($refModule) : 'Manual Journal',
            },
        };
    }

    /**
     * Generate the next unique voucher number formatted as: {prefix}/{financial_year}/{sequence} (e.g. CON/26-27/00001)
     * Reassigns any soft-deleted voucher numbers by picking the lowest available sequence number.
     */
    public static function generateVoucherNumber($plantId, ?string $voucherType = 'Journal', $voucherId = null, $voucherDate = null): string
    {
        $vType = null;
        if (!empty($voucherId)) {
            $vType = VoucherType::find($voucherId);
        }
        if (!$vType && !empty($voucherType)) {
            $vType = VoucherType::where('journal_name', $voucherType)
                ->orWhere('short_code', $voucherType)
                ->first();
        }

        $rawPrefix = $vType && !empty($vType->prefix)
            ? $vType->prefix
            : ($vType && !empty($vType->short_code) ? $vType->short_code : ($voucherType ?: 'J'));
        $prefix = trim($rawPrefix, " /-");

        // Calculate Indian Financial Year (Apr - Mar)
        $date = $voucherDate ?? now();
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        if (!$timestamp) {
            $timestamp = time();
        }

        $month = (int) date('m', $timestamp);
        $year = (int) date('Y', $timestamp);
        $y1 = $month < 4 ? $year - 1 : $year;
        $y2 = $month < 4 ? $year : $year + 1;
        $financialYear = substr((string) $y1, -2) . '-' . substr((string) $y2, -2);

        $pattern = "{$prefix}/{$financialYear}/%";

        // Fetch all active voucher numbers for this plant, prefix, and financial year (excluding soft-deleted)
        $activeVouchers = self::where('plant_id', $plantId)
            ->where('is_deleted', 0)
            ->whereNull('deleted_at')
            ->where('voucher_number', 'like', $pattern)
            ->pluck('voucher_number');

        $usedNumbers = [];
        foreach ($activeVouchers as $vNum) {
            if (preg_match('/\/(\d+)$/', $vNum, $matches)) {
                $usedNumbers[(int) $matches[1]] = true;
            }
        }

        // Find the lowest available sequence number starting from 1 (reuses soft-deleted gaps)
        $nextNum = 1;
        while (isset($usedNumbers[$nextNum])) {
            $nextNum++;
        }

        return "{$prefix}/{$financialYear}/" . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Check if a voucher number is already taken by an active entry (where deleted_at IS NULL and is_deleted = 0).
     */
    public static function isDuplicateVoucherNumber($plantId, string $voucherNumber, $ignoreId = null): bool
    {
        $query = self::where('plant_id', $plantId)
            ->where('voucher_number', $voucherNumber)
            ->where('is_deleted', 0)
            ->whereNull('deleted_at');

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Build default header narration and per-line default narrations according to double-entry accounting conventions.
     *
     * @param array $lines
     * @param string $voucherType
     * @param string $voucherNumber
     * @param string|null $formNarration
     * @return array{header: string, lines: array<int, string>}
     */
    public static function buildDefaultNarrations(array $lines, string $voucherType, string $voucherNumber, ?string $formNarration = null): array
    {
        $accountIds = collect($lines)->pluck('account_id')->filter()->unique();
        $partnerIds = collect($lines)->pluck('partner_id')->filter()->unique();

        $ledgers = Ledger::whereIn('id', $accountIds)->get()->keyBy('id');
        $patrons = $partnerIds->isNotEmpty() ? Patron::whereIn('id', $partnerIds)->get()->keyBy('id') : collect();
        $entities = $partnerIds->isNotEmpty() ? Entity::whereIn('id', $partnerIds)->get()->keyBy('id') : collect();

        $debitAccounts = [];
        $creditAccounts = [];
        $hasCashDebit = false;
        $hasBankDebit = false;
        $hasCashCredit = false;
        $hasBankCredit = false;

        $lineInfos = [];

        // Helper to format common nouns (e.g. Rent, Salary) vs proper nouns (e.g. Ravi)
        $cleanNoun = function (string $name) {
            $lower = strtolower(trim($name));
            if (in_array($lower, ['rent', 'salary', 'salaries', 'interest', 'commission', 'freight', 'carriage', 'discount', 'wages', 'electricity', 'insurance', 'telephone', 'expense', 'expenses'])) {
                return $lower;
            }
            return trim($name);
        };

        foreach ($lines as $idx => $line) {
            $debit = (float) ($line['debit_amount'] ?? 0);
            $credit = (float) ($line['credit_amount'] ?? 0);
            $ledger = $ledgers->get($line['account_id']);
            $patron = !empty($line['partner_id']) ? $patrons->get($line['partner_id']) : null;
            $entity = !empty($line['partner_id']) ? $entities->get($line['partner_id']) : null;

            $accountTitle = $ledger ? trim((string) $ledger->title) : 'Account';
            $displayName = $patron ? trim((string) $patron->legal_name) : ($entity ? trim((string) ($entity->alias ?: $entity->legal_name)) : $accountTitle);

            $isCash = (bool) preg_match('/cash/i', $accountTitle);
            $isBank = (bool) preg_match('/bank/i', $accountTitle);

            if ($debit > 0) {
                $debitAccounts[] = $displayName;
                if ($isCash) $hasCashDebit = true;
                if ($isBank) $hasBankDebit = true;
            } else {
                $creditAccounts[] = $displayName;
                if ($isCash) $hasCashCredit = true;
                if ($isBank) $hasBankCredit = true;
            }

            $lineInfos[$idx] = [
                'is_debit'      => $debit > 0,
                'display_name'  => $displayName,
                'account_title' => $accountTitle,
                'is_cash'       => $isCash,
                'is_bank'       => $isBank,
            ];
        }

        $debitSummary = implode(', ', array_unique($debitAccounts));
        $creditSummary = implode(', ', array_unique($creditAccounts));

        // Determine default overall narrative
        $defaultSummary = null;
        if ($hasCashDebit && !empty($creditSummary)) {
            $defaultSummary = "Being cash received from {$creditSummary}.";
        } elseif ($hasBankDebit && !empty($creditSummary)) {
            $defaultSummary = "Being amount received from {$creditSummary} through bank.";
        } elseif ($hasCashCredit && !empty($debitSummary)) {
            $defaultSummary = "Being " . $cleanNoun($debitSummary) . " paid through cash.";
        } elseif ($hasBankCredit && !empty($debitSummary)) {
            $defaultSummary = "Being " . $cleanNoun($debitSummary) . " paid through bank.";
        } elseif (!empty($debitSummary) && !empty($creditSummary)) {
            $defaultSummary = "Being {$debitSummary} debited against {$creditSummary}.";
        }

        $trimmedFormNarration = trim((string) $formNarration);
        if (!empty($trimmedFormNarration)) {
            $headerNarration = "{$voucherType} {$voucherNumber} - {$trimmedFormNarration}";
        } elseif (!empty($defaultSummary)) {
            $headerNarration = "{$voucherType} {$voucherNumber} - {$defaultSummary}";
        } else {
            $headerNarration = "{$voucherType} {$voucherNumber}";
        }

        $lineNarrations = [];
        foreach ($lines as $idx => $line) {
            // 1. If line_narration was entered by the user in the form, use it directly
            if (!empty(trim((string) ($line['line_narration'] ?? '')))) {
                $lineNarrations[$idx] = trim((string) $line['line_narration']);
                continue;
            }

            $info = $lineInfos[$idx];
            $computedNarration = '';

            if ($info['is_debit']) {
                $opposing = $creditSummary ?: 'Account';
                if ($info['is_cash']) {
                    $computedNarration = "Being cash received from {$opposing}.";
                } elseif ($info['is_bank']) {
                    $computedNarration = "Being amount received from {$opposing} through bank.";
                } elseif ($hasCashCredit) {
                    $computedNarration = "Being " . $cleanNoun($info['display_name']) . " paid through cash.";
                } elseif ($hasBankCredit) {
                    $computedNarration = "Being " . $cleanNoun($info['display_name']) . " paid through bank.";
                } else {
                    $computedNarration = !empty($trimmedFormNarration)
                        ? $trimmedFormNarration
                        : "Being amount debited against {$opposing}.";
                }
            } else {
                $opposing = $debitSummary ?: 'Account';
                if ($info['is_cash']) {
                    $computedNarration = "Being " . $cleanNoun($opposing) . " paid through cash.";
                } elseif ($info['is_bank']) {
                    $computedNarration = "Being " . $cleanNoun($opposing) . " paid through bank.";
                } elseif ($hasCashDebit) {
                    $computedNarration = "Being amount received through Cash.";
                } elseif ($hasBankDebit) {
                    $computedNarration = "Being amount received through bank.";
                } else {
                    $computedNarration = !empty($trimmedFormNarration)
                        ? $trimmedFormNarration
                        : "Being amount credited against {$opposing}.";
                }
            }

            $lineNarrations[$idx] = $computedNarration;
        }

        return [
            'header' => $headerNarration,
            'lines'  => $lineNarrations,
        ];
    }

    /**
     * Resolve ledger_id for a patron:
     * 1. Check mm_patrons.ledger_id
     * 2. Check AccountDefaultSetting for Patron module ('debit_ledger' if debit > 0, 'credit_ledger' if credit > 0)
     * 3. Fallback to Sundry Debtors (debit) / Sundry Creditors (credit)
     */
    public static function resolvePatronLedgerId($plantId, $patronId, float $debitAmount = 0, float $creditAmount = 0): ?int
    {
        $patron = Patron::find($patronId);
        if ($patron && !empty($patron->ledger_id)) {
            return (int) $patron->ledger_id;
        }

        // Setting key: debit_ledger (Sundry Debtors) when debit, credit_ledger (Sundry Creditors) when credit
        $settingKey = $debitAmount > 0 ? 'debit_ledger' : 'credit_ledger';

        $ledgerId = AccountDefaultSetting::where('plant_id', $plantId)
            ->where('module_name', 'Patron')
            ->where('setting_key', $settingKey)
            ->where('is_active', true)
            ->value('ledger_id');

        if (!$ledgerId) {
            $ledgerId = AccountDefaultSetting::where('module_name', 'Patron')
                ->where('setting_key', $settingKey)
                ->where('is_active', true)
                ->value('ledger_id');
        }

        if (!$ledgerId) {
            $ledgerId = AccountDefaultSetting::where('plant_id', $plantId)
                ->where('module_name', 'Patron')
                ->where('setting_key', $settingKey)
                ->value('ledger_id');
        }

        if (!$ledgerId) {
            $fallbackTitle = $debitAmount > 0 ? 'Sundry Debtors' : 'Sundry Creditors';
            $ledgerId = Ledger::where('title', 'like', "%{$fallbackTitle}%")
                ->where(function ($q) use ($plantId) {
                    $q->where('plant_id', $plantId)->orWhereNull('plant_id');
                })
                ->value('id');
        }

        return $ledgerId ? (int) $ledgerId : null;
    }
}
