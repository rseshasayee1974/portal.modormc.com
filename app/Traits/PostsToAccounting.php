<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\OrderTax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait to handle automated accounting postings for Invoices and Bills.
 * Refactored for production stability and better error handling.
 */
trait PostsToAccounting
{
    /**
     * Post the current document to Journal Entries.
     */
    public function postToAccounting(): JournalEntry
    {
        return DB::transaction(function () {
            // 1. Refresh data and ensure tax splits are in sync
            $this->refresh();
            if (method_exists($this, 'syncTaxSplits')) {
                $this->syncTaxSplits();
            }

            // 2. Prepare basic document data
            $docType     = $this->invoice_type ?? 'purchase'; // Assume PO if no invoice_type
            $isSales     = in_array($docType, ['sales', 'invoice']);
            $voucherType = $isSales ? 'SALES' : 'PURCHASE';
            $invoiceNo   = $this->full_number ?? $this->invoice_number ?? $this->po_number ?? '---';
            $invoiceDate = $this->invoice_date ?? $this->date_order ?? now();
            $plantId     = $this->plant_id ?? session('active_plant_id');
            $entityId    = $this->plant->entity_id ?? session('active_entity_id');
            
            // Financial values rounded to 2 decimal places for consistency
            $totalAmount = round((float)($this->total_amount ?? $this->amount_total ?? 0), 2);
            $subtotal    = round((float)($this->subtotal ?? $this->amount_untaxed ?? 0), 2);
            $taxTotal    = round((float)($this->tax_amount ?? $this->amount_tax ?? 0), 2);

            // 3. Create/Update Journal Entry Header
            if (isset($this->po_number)) {
                $refModule = 'purchase_order';
            } else {
                $refModule = ($docType === 'bill') ? 'bill' : 'invoice';
            }
            
            $journalEntry = JournalEntry::updateOrCreate(
                ['ref_module' => $refModule, 'ref_id' => $this->id, 'plant_id' => $plantId],
                [
                    'entity_id'      => $entityId,
                    'voucher_type'   => $voucherType,
                    'voucher_number' => $invoiceNo,
                    'voucher_date'   => $invoiceDate,
                    'posting_date'   => $invoiceDate,
                    'narration'      => ($isSales ? "Sales Invoice: " : (isset($this->po_number) ? "Purchase Order: " : "Purchase Bill: ")) . $invoiceNo . " | " . ($this->partner?->legal_name ?? $this->vendor?->legal_name ?? 'Unknown Partner'),
                    'total_debit'    => $totalAmount,
                    'total_credit'   => $totalAmount,
                    'is_status'      => 'DRAFT',
                    'created_by'     => Auth::id() ?? 1,
                ]
            );

            // 4. Clear existing lines
            $journalEntry->lines()->delete();

            $lines = [];

            // --- DEBIT/CREDIT RULE 1: Partner Ledger (Customer/Vendor) ---
            $partner = $this->partner ?? $this->vendor;
            $partnerLedgerId = $partner?->ledger_id;
            if (!$partnerLedgerId) {
                $partnerLedgerId = $this->getAccountingLedgerId($isSales ? 'debit_ledger' : 'credit_ledger', 'Sundry');
            }

            if (!$partnerLedgerId) {
                throw new \Exception("Accounting Failure: Missing Partner Ledger for " . ($partner?->legal_name ?? 'Unknown'));
            }

            $lines[] = [
                'account_id'     => $partnerLedgerId,
                'debit_amount'   => $isSales ? $totalAmount : 0,
                'credit_amount'  => $isSales ? 0 : $totalAmount,
                'partner_type'   => 'Patron',
                'partner_id'     => $this->partner_id ?? $this->vendor_id,
                'narration_name' => $isSales ? 'Receivable' : 'Payable',
                'line_narration' => "Invoice #{$invoiceNo}",
            ];

            // --- DEBIT/CREDIT RULE 2: Revenue / Expense Ledger ---
            $baseLedgerId = $this->account_id;
            if (!$baseLedgerId) {
                $baseLedgerId = $this->getAccountingLedgerId($isSales ? 'sales_account' : 'purchase_account', $isSales ? 'Sales' : 'Purchase');
            }

            if (!$baseLedgerId && $subtotal != 0) {
                $accountType = $isSales ? 'Sales' : 'Purchase';
                throw new \Exception("Accounting Failure: Missing {$accountType} Ledger. Please map it in Account Default Settings.");
            }

            if ($baseLedgerId && $subtotal != 0) {
                $lines[] = [
                    'account_id'     => $baseLedgerId,
                    'debit_amount'   => $isSales ? 0 : $subtotal,
                    'credit_amount'  => $isSales ? $subtotal : 0,
                    'narration_name' => $isSales ? 'Revenue' : 'Expense',
                    'line_narration' => "Base amount for #{$invoiceNo}",
                ];
            }

            // --- DEBIT/CREDIT RULE 3: Tax Splits ---
            // We use direct query to bypass any relationship caching issues
            $orderTaxes = OrderTax::where('order_id', '=',$this->id)
                ->whereIn('order_type', ['Invoice', 'App\Models\Invoice', $docType, 'PurchaseOrder'])
                ->get();

            $sumTaxLines = 0;
            foreach ($orderTaxes as $taxSplit) {
                $taxAmt = round((float)($taxSplit->amount ?? 0), 2);
                if ($taxAmt == 0) continue;

                $taxAccountId = $taxSplit->account_id;
                if (!$taxAccountId) {
                    $taxName = strtolower($taxSplit->name ?? '');
                    $settingKey = null;
                    if (str_contains($taxName, 'cgst')) $settingKey = $isSales ? 'cgst_output' : 'cgst_input';
                    elseif (str_contains($taxName, 'sgst')) $settingKey = $isSales ? 'sgst_output' : 'sgst_input';
                    elseif (str_contains($taxName, 'igst')) $settingKey = $isSales ? 'igst_output' : 'igst_input';
                    
                    if ($settingKey) {
                        $taxAccountId = $this->getAccountingLedgerId($settingKey, 'GST');
                    }
                }

                if ($taxAccountId) {
                    $lines[] = [
                        'account_id'     => $taxAccountId,
                        'debit_amount'   => $isSales ? 0 : $taxAmt,
                        'credit_amount'  => $isSales ? $taxAmt : 0,
                        'tax_id'         => $taxSplit->tax_id,
                        'narration_name' => $taxSplit->name,
                        'line_narration' => $taxSplit->name . " on #{$invoiceNo}",
                    ];
                    $sumTaxLines += $taxAmt;
                }
            }

            // Safety check: if tax lines don't match invoice tax_amount, use fallback
            if (round($sumTaxLines, 2) != $taxTotal && $taxTotal != 0) {
                $remainingTax = round($taxTotal - $sumTaxLines, 2);
                $fallbackTaxLedgerId = $this->getAccountingLedgerId('tax_account', 'Tax');
                if ($fallbackTaxLedgerId) {
                    $lines[] = [
                        'account_id'     => $fallbackTaxLedgerId,
                        'debit_amount'   => $isSales ? 0 : $remainingTax,
                        'credit_amount'  => $isSales ? $remainingTax : 0,
                        'narration_name' => 'Tax Adjustment',
                        'line_narration' => 'Consolidated Tax for #{$invoiceNo}',
                    ];
                } else {
                    throw new \Exception("Accounting Failure: Missing Tax Ledger for adjustment. Please map 'tax_account' in Account Default Settings.");
                }
            }

            // --- DEBIT/CREDIT RULE 4: Shipping, Adjustment, Round Off ---
            $this->addAdjustmentLines($lines, $isSales, $invoiceNo);

            // 5. Final Balancing & Persistence
            $totalDebitActual = 0;
            $totalCreditActual = 0;

            foreach ($lines as $lineData) {
                $lineData['journal_entry_id'] = $journalEntry->id;
                $lineData['plant_id']         = $plantId;
                $lineData['created_by']       = Auth::id() ?? 1;
                
                JournalEntryLine::create($lineData);

                $totalDebitActual += round((float)$lineData['debit_amount'], 2);
                $totalCreditActual += round((float)$lineData['credit_amount'], 2);
            }

            // FINAL BALANCING CHECK (Precision threshold: 0.01)
            $difference = round(abs($totalDebitActual - $totalCreditActual), 2);
            if ($difference > 0.05) {
                Log::error("Accounting Unbalanced: Invoice ID {$this->id}", [
                    'expected' => $totalAmount,
                    'actual_debit' => $totalDebitActual,
                    'actual_credit' => $totalCreditActual,
                    'lines_count' => count($lines)
                ]);
                throw new \Exception("Accounting Failure: Transaction is unbalanced by {$difference}. (Total Debit: {$totalDebitActual}, Total Credit: {$totalCreditActual}). Posting aborted.");
            }

            // Update header and finalize
            $journalEntry->update([
                'total_debit'  => $totalDebitActual,
                'total_credit' => $totalCreditActual,
                'is_status'    => 'POSTED',
            ]);

            return $journalEntry;
        });
    }

    /**
     * Helper for Adjustments, Shipping, and Rounding
     */
    private function addAdjustmentLines(&$lines, $isSales, $invoiceNo)
    {
        $map = [
            'shipping_charges' => ['key' => 'shipping_account', 'fallback' => 'Shipping', 'invert' => false],
            'adjustment'       => ['key' => 'adjustment_account', 'fallback' => 'Adjustment', 'invert' => false],
            'round_off'        => ['key' => 'round_off_account', 'fallback' => 'Round Off', 'invert' => false],
            'global_discount'  => ['key' => 'discount_account', 'fallback' => 'Discount', 'invert' => true],
        ];

        foreach ($map as $field => $config) {
            $raw_value = round((float)($this->{$field} ?? 0), 2);
            if ($raw_value == 0) continue;

            // Invert the effect if it's a discount (which reduces the total instead of adding to it)
            $value = $config['invert'] ? -$raw_value : $raw_value;

            $ledgerId = $this->getAccountingLedgerId($config['key'], $config['fallback']);
            if ($ledgerId) {
                $absVal = abs($value);
                // For Sales: Positive Shipping is Credit (Income), Negative Adjustment is Debit (Expense)
                // Logic: Does this increase or decrease the partner balance?
                $isDebitLine = ($value < 0);
                if (!$isSales) $isDebitLine = ($value > 0);

                $lines[] = [
                    'account_id'     => $ledgerId,
                    'debit_amount'   => $isDebitLine ? $absVal : 0,
                    'credit_amount'  => $isDebitLine ? 0 : $absVal,
                    'narration_name' => $config['fallback'],
                    'line_narration' => "{$config['fallback']} for #{$invoiceNo}",
                ];
            } else {
                throw new \Exception("Accounting Failure: Missing posting account for '{$field}'. Please map '{$config['key']}' in Account Default Settings.");
            }
        }
    }

    /**
     * Helper to find a ledger by mapping or search.
     */
    protected function getAccountingLedgerId(string $key, string $fallbackSearch): ?int
    {
        $docType = $this->invoice_type ?? 'purchase';
        $module = in_array($docType, ['sales', 'invoice']) ? 'Invoice' : 'Purchase';
        $plantId = $this->plant_id ?? session('active_plant_id');
        
        $mapped = \App\Models\AccountDefaultSetting::where('plant_id', $plantId)
            ->where('module_name', $module)
            ->where('setting_key', $key)
            ->where('is_active', true)
            ->value('ledger_id');
            
        if ($mapped) return $mapped;

        return Ledger::where('title', 'like', "%{$fallbackSearch}%")
            ->where('plant_id', $plantId)
            ->value('id');
    }
}