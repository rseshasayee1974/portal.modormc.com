<?php

namespace App\Traits;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to handle automated accounting postings for Invoices and Bills.
 * Follows double-entry bookkeeping standards (similar to Tally/ERP logic).
 */
trait PostsToAccounting
{
    /**
     * Post the current document to Journal Entries.
     * 
     * Sales (Invoice) Flow:
     * - DEBIT  Customer Ledger
     * - CREDIT Sales/Revenue Ledger
     * - CREDIT GST Output Ledgers
     * 
     * Purchase (Bill) Flow:
     * - DEBIT  Purchase/Expense Ledger
     * - DEBIT  GST Input Ledgers
     * - CREDIT Vendor Ledger
     * 
     * @return JournalEntry
     */
    public function postToAccounting(): JournalEntry
    {
        return DB::transaction(function () {
            // Local variables to avoid "Undefined property" errors
            $isSales        = (($this->invoice_type ?? 'sales') === 'sales' || ($this->invoice_type ?? '') === 'invoice');
            $voucherType    = $isSales ? 'SALES' : 'PURCHASE';
            $invoiceNo      = $this->full_number ?? $this->invoice_number ?? '---';
            $invoiceDate    = $this->invoice_date ?? now();
            $totalAmount    = (float) ($this->total_amount ?? 0);
            $subtotal       = (float) ($this->subtotal ?? 0);
            $refTitle       = $this->ref_title ?? '---';
            $plantId        = $this->plant_id ?? session('active_plant_id');
            $entityId       = $this->plant->entity_id ?? session('active_entity_id');
            $partnerId      = $this->partner_id ?? null;
            $accountId      = $this->account_id ?? null;
            
            // 1. Create or Update Journal Entry Header
            $refModule = ($this->invoice_type ?? 'invoice') === 'bill' ? 'bill' : 'invoice';

            $journalEntry = JournalEntry::updateOrCreate(
                [
                    'ref_module' => $refModule,
                    'ref_id'     => $this->id,
                    'plant_id'   => $plantId,
                ],
                [
                    'entity_id'      => $entityId,
                    'voucher_type'   => $voucherType,
                    'voucher_number' => $invoiceNo,
                    'voucher_date'   => $invoiceDate,
                    'posting_date'   => $invoiceDate,
                    'narration'      => ($isSales ? "Sales Invoice: " : "Purchase Bill: ") . $invoiceNo . " | Ref: " . $refTitle,
                    'total_debit'    => $totalAmount,
                    'total_credit'   => $totalAmount,
                    'is_status'      => 'DRAFT', // Set to DRAFT until balanced
                    'created_by'     => Auth::id() ?? 1,
                ]
            );

            // 2. Clear existing lines
            $journalEntry->lines()->delete();

            $lines = [];

            // RULE 1: Partner Ledger (Customer/Vendor) - Total Invoice Amount
            $partnerLedgerId = $this->partner?->ledger_id;
            
            // If explicit partner ledger missing, try to find default from settings
            if (!$partnerLedgerId) {
                $settingKey = $isSales ? 'debit_ledger' : 'credit_ledger';
                $partnerLedgerId = $this->getAccountingLedgerId($settingKey, 'Sundry');
            }

            if ($partnerLedgerId) {
                $lines[] = [
                    'account_id'     => $partnerLedgerId,
                    'debit_amount'   => $isSales ? $totalAmount : 0,
                    'credit_amount'  => $isSales ? 0 : $totalAmount,
                    'partner_type'   => 'Patron',
                    'partner_id'     => $partnerId,
                    'narration_name' => $isSales ? 'Amount Receivable' : 'Amount Payable',
                    'line_narration' => $isSales ? 'Amount Receivable' : 'Amount Payable',
                ];
            } else {
                throw new \Exception("Accounting Failure: No Ledger found for Partner (Customer/Vendor). Please map 'Patron' ledgers in settings.");
            }

            // RULE 2: Revenue / Expense Ledger (Subtotal)
            if ($accountId) {
                $lines[] = [
                    'account_id'     => $accountId,
                    'debit_amount'   => $isSales ? 0 : $subtotal,
                    'credit_amount'  => $isSales ? $subtotal : 0,
                    'narration_name' => $isSales ? 'Base Revenue' : 'Base Expense',
                    'line_narration' => $isSales ? 'Base Revenue' : 'Base Expense',
                ];
            } else {
                // Try to find default sales/purchase account if missing on model
                $settingKey = $isSales ? 'sales_account' : 'purchase_account';
                $fallbackLedgerId = $this->getAccountingLedgerId($settingKey, $isSales ? 'Sales' : 'Purchase');
                if ($fallbackLedgerId) {
                    $lines[] = [
                        'account_id'     => $fallbackLedgerId,
                        'debit_amount'   => $isSales ? 0 : $subtotal,
                        'credit_amount'  => $isSales ? $subtotal : 0,
                        'narration_name' => $isSales ? 'Base Revenue' : 'Base Expense',
                        'line_narration' => $isSales ? 'Base Revenue' : 'Base Expense',
                    ];
                }
            }

            // RULE 3: Tax Splits
            $orderTaxes = $this->orderTaxes ?? [];
            foreach ($orderTaxes as $taxSplit) {
                $taxAmt = (float) ($taxSplit->amount ?? 0);
                if ($taxAmt == 0) continue;
                
                $taxAccountId = $taxSplit->account_id;

                // Fallback to Default Settings if account_id is missing on the tax split
                if (!$taxAccountId) {
                    $taxName = strtolower($taxSplit->name ?? '');
                    $settingKey = null;

                    if (str_contains($taxName, 'cgst')) {
                        $settingKey = $isSales ? 'cgst_output' : 'cgst_input';
                    } elseif (str_contains($taxName, 'sgst')) {
                        $settingKey = $isSales ? 'sgst_output' : 'sgst_input';
                    } elseif (str_contains($taxName, 'igst')) {
                        $settingKey = $isSales ? 'igst_output' : 'igst_input';
                    }

                    if ($settingKey) {
                        $taxAccountId = $this->getAccountingLedgerId($settingKey, $isSales ? 'GST Output' : 'GST Input');
                    }
                }

                if ($taxAccountId) {
                    $lines[] = [
                        'account_id'     => $taxAccountId,
                        'debit_amount'   => $isSales ? 0 : $taxAmt,
                        'credit_amount'  => $isSales ? $taxAmt : 0,
                        'tax_id'         => $taxSplit->tax_id,
                        'narration_name' => $taxSplit->name,
                        'line_narration' => $taxSplit->name,
                    ];
                } else {
                    // If still no account, we might have a problem balancing, but we skip to avoid null account_id
                }
            }

            // RULE 4: Shipping Charges
            $shippingCharges = (float) ($this->shipping_charges ?? 0);
            if ($shippingCharges > 0) {
                $shippingLedgerId = $this->getAccountingLedgerId('shipping_account', 'Shipping');
                if ($shippingLedgerId) {
                    $lines[] = [
                        'account_id'     => $shippingLedgerId,
                        'debit_amount'   => $isSales ? 0 : $shippingCharges,
                        'credit_amount'  => $isSales ? $shippingCharges : 0,
                        'narration_name' => 'Shipping/Freight Charges',
                        'line_narration' => 'Shipping/Freight Charges',
                    ];
                }
            }

            // RULE 5: Adjustments
            $adjustmentValue = (float) ($this->adjustment ?? 0);
            if ($adjustmentValue != 0) {
                $adjLedgerId = $this->getAccountingLedgerId('adjustment_account', 'Adjustment');
                if ($adjLedgerId) {
                    $val = abs($adjustmentValue);
                    $isDebit = $isSales ? ($adjustmentValue < 0) : ($adjustmentValue > 0);
                    $lines[] = [
                        'account_id'     => $adjLedgerId,
                        'debit_amount'   => $isDebit ? $val : 0,
                        'credit_amount'  => $isDebit ? 0 : $val,
                        'narration_name' => 'Other Adjustments',
                        'line_narration' => 'Other Adjustments',
                    ];
                }
            }

            // RULE 6: Round Off
            $roundOffValue = (float) ($this->round_off ?? 0);
            if ($roundOffValue != 0) {
                $roundOffLedgerId = $this->getAccountingLedgerId('round_off_account', 'Round Off');
                if ($roundOffLedgerId) {
                    $val = abs($roundOffValue);
                    $isDebit = $isSales ? ($roundOffValue < 0) : ($roundOffValue > 0);
                    $lines[] = [
                        'account_id'     => $roundOffLedgerId,
                        'debit_amount'   => $isDebit ? $val : 0,
                        'credit_amount'  => $isDebit ? 0 : $val,
                        'narration_name' => 'Rounding',
                        'line_narration' => 'Rounding',
                    ];
                }
            }

            // RULE 7: TDS
            $tdsAmount = (float) ($this->tds_amount ?? 0);
            if ($tdsAmount > 0) {
                $tdsSettingKey = $isSales ? 'tds_receivable' : 'tds_payable';
                $tdsLedgerId = $this->getAccountingLedgerId($tdsSettingKey, 'TDS');
                
                if ($tdsLedgerId) {
                    $lines[] = [
                        'account_id'     => $tdsLedgerId,
                        'debit_amount'   => $isSales ? $tdsAmount : 0,
                        'credit_amount'  => $isSales ? 0 : $tdsAmount,
                        'narration_name' => $isSales ? 'TDS Receivable' : 'TDS Payable',
                        'line_narration' => $isSales ? 'TDS Receivable' : 'TDS Payable',
                    ];

                    // Adjust Rule 1 (Partner) balance
                    foreach ($lines as &$line) {
                        if (($line['partner_id'] ?? null) == $partnerId && ($line['partner_type'] ?? null) == 'Patron') {
                            if ($isSales) {
                                $line['debit_amount'] -= $tdsAmount;
                            } else {
                                $line['credit_amount'] -= $tdsAmount;
                            }
                        }
                    }
                }
            }

            // 4. Persistence & Balancing Verification
            $totalDebitActual = 0;
            $totalCreditActual = 0;

            foreach ($lines as $lineData) {
                $lineData['journal_entry_id'] = $journalEntry->id;
                $lineData['plant_id']         = $plantId;
                $lineData['created_by']       = Auth::id() ?? 1;
                
                JournalEntryLine::create($lineData);

                $totalDebitActual += round((float)$lineData['debit_amount'], 4);
                $totalCreditActual += round((float)$lineData['credit_amount'], 4);
            }

            // FINAL BALANCING CHECK
            $difference = abs($totalDebitActual - $totalCreditActual);
            if ($difference > 0.01) {
                throw new \Exception("Accounting Failure: Transaction is unbalanced by {$difference}. (Total Debit: {$totalDebitActual}, Total Credit: {$totalCreditActual}). Posting aborted to prevent ledger inconsistency.");
            }

            // Update header with actual totals and mark as POSTED
            $journalEntry->update([
                'total_debit'  => $totalDebitActual,
                'total_credit' => $totalCreditActual,
                'is_status'    => 'POSTED',
            ]);

            return $journalEntry;
        });
    }

    /**
     * Helper to find a ledger by name or common title if not explicitly set.
     */
    protected function getAccountingLedgerId(string $key, string $fallbackSearch): ?int
    {
        $module = (($this->invoice_type ?? 'sales') === 'sales' || ($this->invoice_type ?? '') === 'invoice') ? 'Invoice' : 'Purchase';
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
