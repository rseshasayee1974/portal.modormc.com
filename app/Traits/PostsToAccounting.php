<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\OrderTax;
use App\Models\AccountDefaultSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared accounting-posting logic for invoice-like models
 * (sales, purchase, bill, dispatch, expense, stock in/out, etc).
 *
 * Mix this trait into any model that needs to post itself
 * as a JournalEntry (Invoice, PurchaseOrder, Bill, ...).
 */
trait PostsToAccounting
{
    /** Balancing tolerance when comparing total debit vs total credit. */
    protected const BALANCE_TOLERANCE = 0.05;

    /** Doc-type => voucher type used on the JournalEntry header. */
    protected static array $voucherTypeMap = [
        'sales'               => 'SALES',
        'invoice'             => 'SALES',
        'purchase'            => 'PURCHASE',
        'bill'                => 'PURCHASE',
        'dispatch'            => 'SALES',
        'expense'             => 'JOURNAL',
        'entries'             => 'JOURNAL',
        'stockin'             => 'JOURNAL',
        'stockout'            => 'JOURNAL',
        'machine maintenance' => 'JOURNAL',
    ];

    /** Doc-type => module name used for OrderTax lookups and ledger mapping. */
    protected static array $moduleMap = [
        'sales'               => 'Invoice',
        'invoice'             => 'Invoice',
        'purchase'            => 'Purchase',
        'bill'                => 'Purchase',
        'dispatch'            => 'Dispatch',
        'expense'             => 'Expense',
        'entries'             => 'Entries',
        'stockin'             => 'StockIn',
        'stockout'            => 'StockOut',
        'machine maintenance' => 'Machine Maintenance',
    ];

    /** Doc-types treated as sales-side (debit partner / credit revenue). */
    protected static array $salesDocTypes = ['sales', 'invoice', 'dispatch', 'stockout'];

    /** Adjustment-style fields posted after the main lines. */
    protected static array $adjustmentFieldMap = [
        'shipping_charges' => ['key' => 'shipping_account',  'fallback' => 'Shipping',  'invert' => false],
        'adjustment'       => ['key' => 'adjustment_account', 'fallback' => 'Adjustment', 'invert' => false],
        'round_off'        => ['key' => 'round_off_account',  'fallback' => 'Round Off',  'invert' => false],
        'global_discount'  => ['key' => 'discount_account',   'fallback' => 'Discount',   'invert' => true],
    ];

    /**
     * Post this document to accounting as a balanced JournalEntry.
     */
    public function postToAccounting(): JournalEntry
    {
        return DB::transaction(function () {
            $this->refresh();
            if (method_exists($this, 'syncTaxSplits')) {
                $this->syncTaxSplits();
            }

            $ctx = $this->buildPostingContext();

            $journalEntry = $this->createOrUpdateJournalHeader($ctx);

            $journalEntry->lines()->delete();

            $lines = [];
            $lines[] = $this->buildPartnerLine($ctx);

            if ($revenueLine = $this->buildRevenueLine($ctx)) {
                $lines[] = $revenueLine;
            }

            $lines = array_merge($lines, $this->buildTaxLines($ctx));
            $this->addAdjustmentLines($lines, $ctx['isSales'], $ctx['invoiceNo']);

            [$totalDebit, $totalCredit] = $this->persistLines($journalEntry, $lines, $ctx['plantId']);

            $this->assertBalanced($totalDebit, $totalCredit, $ctx);

            $journalEntry->update([
                'total_debit'  => $totalDebit,
                'total_credit' => $totalCredit,
                'is_status'    => 'POSTED',
            ]);

            return $journalEntry;
        });
    }

    /**
     * Gather and normalize all the values needed to build the journal entry,
     * so nothing downstream re-reads $this or re-derives docType/module.
     */
    protected function buildPostingContext(): array
    {
        $docType = strtolower($this->invoice_type ?? 'purchase');
        $isSales = in_array($docType, static::$salesDocTypes, true);

        $partner = $this->partner ?? $this->vendor ?? $this->customer
            ?? $this->transport ?? $this->transporter;

        $hasPoNumber = isset($this->po_number);

        return [
            'docType'     => $docType,
            'isSales'     => $isSales,
            'voucherType' => static::$voucherTypeMap[$docType] ?? ($isSales ? 'SALES' : 'PURCHASE'),
            'module'      => static::$moduleMap[$docType] ?? ucfirst($docType),
            'invoiceNo'   => $this->full_number ?? $this->invoice_number ?? $this->po_number ?? $this->ref_no ?? '---',
            'invoiceDate' => $this->invoice_date ?? $this->date_order ?? now(),
            'plantId'     => $this->plant_id ?? session('active_plant_id'),
            'entityId'    => $this->plant->entity_id ?? session('active_entity_id'),
            'totalAmount' => round((float) ($this->total_amount ?? $this->amount_total ?? 0), 2),
            'subtotal'    => round((float) ($this->subtotal ?? $this->amount_untaxed ?? 0), 2),
            'taxTotal'    => round((float) ($this->tax_amount ?? $this->amount_tax ?? 0), 2),
            'refModule'   => $hasPoNumber
                ? 'purchase_order'
                : ($docType === 'bill' ? 'bill' : ($docType === 'sales' ? 'invoice' : $docType)),
            'partner'     => $partner,
            'partnerId'   => $this->partner_id ?? $this->vendor_id ?? $this->customer_id
                ?? $this->transport_id ?? $this->transporter_id,
        ];
    }

    protected function createOrUpdateJournalHeader(array $ctx): JournalEntry
    {
        $label = isset($this->po_number) ? 'Purchase Order: ' : ucfirst($ctx['docType']) . ': ';
        $partnerName = $ctx['partner']?->legal_name ?? 'Unknown Partner';

        return JournalEntry::updateOrCreate(
            [
                'ref_module' => $ctx['refModule'],
                'ref_id'     => $this->id,
                'plant_id'   => $ctx['plantId'],
            ],
            [
                'entity_id'      => $ctx['entityId'],
                'voucher_type'   => $ctx['voucherType'],
                'voucher_number' => $ctx['invoiceNo'],
                'voucher_date'   => $ctx['invoiceDate'],
                'posting_date'   => $ctx['invoiceDate'],
                'narration'      => $label . $ctx['invoiceNo'] . ' | ' . $partnerName,
                'total_debit'    => $ctx['totalAmount'],
                'total_credit'   => $ctx['totalAmount'],
                'is_status'      => 'DRAFT',
                'created_by'     => Auth::id() ?? 1,
            ]
        );
    }

    /**
     * Rule 1: Partner ledger (customer / vendor / transporter).
     */
    protected function buildPartnerLine(array $ctx): array
    {
        $ledgerId = $ctx['partner']?->ledger_id
            ?? $this->getAccountingLedgerId($ctx['isSales'] ? 'debit_ledger' : 'credit_ledger');

        if (!$ledgerId) {
            throw new \Exception(
                'Accounting Failure: Missing Partner Ledger for ' . ($ctx['partner']?->legal_name ?? 'Unknown')
            );
        }

        return [
            'account_id'     => $ledgerId,
            'debit_amount'   => $ctx['isSales'] ? $ctx['totalAmount'] : 0,
            'credit_amount'  => $ctx['isSales'] ? 0 : $ctx['totalAmount'],
            'partner_type'   => 'Patron',
            'partner_id'     => $ctx['partnerId'],
            'narration_name' => $ctx['isSales'] ? 'Receivable' : 'Payable',
            'line_narration' => "Invoice #{$ctx['invoiceNo']}",
        ];
    }

    /**
     * Rule 2: Revenue / expense ledger. Returns null when there's nothing to post.
     */
    protected function buildRevenueLine(array $ctx): ?array
    {
        if ($ctx['subtotal'] == 0) {
            return null;
        }

        $ledgerId = $this->account_id
            ?? $this->getAccountingLedgerId($ctx['isSales'] ? 'sales_account' : 'purchase_account');

        if (!$ledgerId) {
            $accountType = $ctx['isSales'] ? 'Sales' : 'Purchase';
            throw new \Exception("Accounting Failure : Missing {$accountType} Ledger. Please map it in Account Default Settings.");
        }

        return [
            'account_id'     => $ledgerId,
            'debit_amount'   => $ctx['isSales'] ? 0 : $ctx['subtotal'],
            'credit_amount'  => $ctx['isSales'] ? $ctx['subtotal'] : 0,
            'narration_name' => $ctx['isSales'] ? 'Revenue' : 'Expense',
            'line_narration' => "Base amount for #{$ctx['invoiceNo']}",
        ];
    }

    /**
     * Rule 3: Tax split lines, plus a fallback adjustment line if the
     * individual splits don't reconcile with the invoice's tax_amount.
     */
    protected function buildTaxLines(array $ctx): array
    {
        $lines = [];
        $sumTaxLines = 0;

        $orderTaxes = OrderTax::query()
            ->where('order_id', $this->id)
            ->where('order_type', $ctx['module'])
            ->get();

        foreach ($orderTaxes as $taxSplit) {
            $taxAmt = round((float) ($taxSplit->amount ?? 0), 2);
            if ($taxAmt == 0) {
                continue;
            }

            $taxAccountId = $taxSplit->account_id ?? $this->resolveGstLedgerId($taxSplit->name, $ctx['isSales']);

            if ($taxAccountId) {
                $lines[] = [
                    'account_id'     => $taxAccountId,
                    'debit_amount'   => $ctx['isSales'] ? 0 : $taxAmt,
                    'credit_amount'  => $ctx['isSales'] ? $taxAmt : 0,
                    'tax_id'         => $taxSplit->tax_id,
                    'narration_name' => $taxSplit->name,
                    'line_narration' => "{$taxSplit->name} on #{$ctx['invoiceNo']}",
                ];
                $sumTaxLines += $taxAmt;
            }
        }

        if (round($sumTaxLines, 2) != $ctx['taxTotal'] && $ctx['taxTotal'] != 0) {
            $remainingTax = round($ctx['taxTotal'] - $sumTaxLines, 2);
            $fallbackLedgerId = $this->getAccountingLedgerId('tax_account');

            if (!$fallbackLedgerId) {
                throw new \Exception("Accounting Failure: Missing Tax Ledger for adjustment. Please map 'tax_account' in Account Default Settings.");
            }

            $lines[] = [
                'account_id'     => $fallbackLedgerId,
                'debit_amount'   => $ctx['isSales'] ? 0 : $remainingTax,
                'credit_amount'  => $ctx['isSales'] ? $remainingTax : 0,
                'narration_name' => 'Tax Adjustment',
                'line_narration' => "Consolidated Tax for #{$ctx['invoiceNo']}",
            ];
        }

        return $lines;
    }

    /**
     * Map a tax split's name (CGST/SGST/IGST) to its output/input ledger setting key.
     */
    protected function resolveGstLedgerId(?string $taxName, bool $isSales): ?int
    {
        $taxName = strtolower($taxName ?? '');

        $settingKey = match (true) {
            str_contains($taxName, 'cgst') => $isSales ? 'cgst_output' : 'cgst_input',
            str_contains($taxName, 'sgst') => $isSales ? 'sgst_output' : 'sgst_input',
            str_contains($taxName, 'igst') => $isSales ? 'igst_output' : 'igst_input',
            default => null,
        };

        return $settingKey ? $this->getAccountingLedgerId($settingKey) : null;
    }

    /**
     * Rule 4: Shipping, adjustment, round off, and global discount lines.
     */
    protected function addAdjustmentLines(array &$lines, bool $isSales, string $invoiceNo): void
    {
        foreach (static::$adjustmentFieldMap as $field => $config) {
            $rawValue = round((float) ($this->{$field} ?? 0), 2);
            if ($rawValue == 0) {
                continue;
            }

            // Discounts reduce the total rather than add to it.
            $value = $config['invert'] ? -$rawValue : $rawValue;

            $ledgerId = $this->getAccountingLedgerId($config['key']);
            if (!$ledgerId) {
                throw new \Exception("Accounting Failure: Missing posting account for '{$field}'. Please map '{$config['key']}' in Account Default Settings.");
            }

            $absValue = abs($value);
            $isDebitLine = $isSales ? ($value < 0) : ($value > 0);

            $lines[] = [
                'account_id'     => $ledgerId,
                'debit_amount'   => $isDebitLine ? $absValue : 0,
                'credit_amount'  => $isDebitLine ? 0 : $absValue,
                'narration_name' => $config['fallback'],
                'line_narration' => "{$config['fallback']} for #{$invoiceNo}",
            ];
        }
    }

    /**
     * Persist all lines and return [totalDebit, totalCredit].
     */
    protected function persistLines(JournalEntry $journalEntry, array $lines, ?int $plantId): array
    {
        $narrationLabel = null;
        $docType = strtolower($this->invoice_type ?? 'purchase');
        if ($docType === 'bill') {
            $narrationLabel = !empty($this->ref_id) ? 'purchase' : 'manual';
        }

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $lineData) {
            $lineData['journal_entry_id'] = $journalEntry->id;
            $lineData['plant_id']         = $plantId;
            $lineData['created_by']       = Auth::id() ?? 1;
            $lineData['narration_label']  = $narrationLabel;

            JournalEntryLine::create($lineData);

            $totalDebit  += round((float) $lineData['debit_amount'], 2);
            $totalCredit += round((float) $lineData['credit_amount'], 2);
        }

        return [$totalDebit, $totalCredit];
    }

    /**
     * Guard against silently posting an unbalanced journal entry.
     */
    protected function assertBalanced(float $totalDebit, float $totalCredit, array $ctx): void
    {
        $difference = round(abs($totalDebit - $totalCredit), 2);

        if ($difference > static::BALANCE_TOLERANCE) {
            Log::error("Accounting Unbalanced: Invoice ID {$this->id}", [
                'expected'       => $ctx['totalAmount'],
                'actual_debit'   => $totalDebit,
                'actual_credit'  => $totalCredit,
            ]);

            throw new \Exception(
                "Accounting Failure: Transaction is unbalanced by {$difference}. " .
                "(Total Debit: {$totalDebit}, Total Credit: {$totalCredit}). Posting aborted."
            );
        }
    }

    /**
     * Resolve a ledger id strictly from AccountDefaultSetting mapping.
     *
     * No name-based guessing fallback: if the plant hasn't explicitly
     * mapped this setting key, callers get null and are expected to
     * raise a clear "please map it" error rather than silently posting
     * to a ledger found by a loose title search.
     */
    protected function getAccountingLedgerId(string $key): ?int
    {
        $docType = strtolower($this->invoice_type ?? 'purchase');
        $module  = static::$moduleMap[$docType] ?? ucfirst($docType);
        $plantId = $this->plant_id ?? session('active_plant_id');

        return AccountDefaultSetting::query()
            ->where('plant_id', $plantId)
            ->where('module_name', $module)
            ->where('setting_key', $key)
            ->where('is_active', true)
            ->value('ledger_id');
    }
}