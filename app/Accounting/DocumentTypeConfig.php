<?php

declare(strict_types=1);

namespace App\Accounting;

use App\Exceptions\AccountingException;

/**
 * Single source of truth for debit/credit side per document type.
 *
 * partner_side : which side the partner (customer/vendor) goes on
 * base_side    : which side the revenue/expense/bank account goes on
 * tax_side     : which side tax lines go on
 *
 * This eliminates the $isSales boolean that was scattered throughout the old code.
 * Adding a new doc type = add one config block here, nothing else changes.
 */
class DocumentTypeConfig
{
    private const CONFIG = [
        'invoice' => [
            'voucher_type'    => 'SALES',
            'partner_side'    => 'debit',    // AR: customer owes us
            'base_side'       => 'credit',   // Revenue credited
            'tax_side'        => 'credit',   // Output GST credited
            'partner_setting' => 'debit_ledger',
            'base_setting'    => 'sales_account',
            'module'          => 'Invoice',
        ],
        'bill' => [
            'voucher_type'    => 'PURCHASE',
            'partner_side'    => 'credit',   // AP: we owe vendor
            'base_side'       => 'debit',    // Expense debited
            'tax_side'        => 'debit',    // Input GST debited
            'partner_setting' => 'credit_ledger',
            'base_setting'    => 'purchase_account',
            'module'          => 'Purchase', // Note: Using 'Purchase' to match Database module_name (not 'Bill')
        ],
        'expense' => [
            'voucher_type'    => 'JOURNAL',
            'partner_side'    => 'credit',   // Payable to expense party
            'base_side'       => 'debit',    // Expense debited
            'tax_side'        => 'debit',    // Input GST debited
            'partner_setting' => 'credit_ledger',
            'base_setting'    => 'expense_account',
            'module'          => 'Expense',
        ],
        'payment' => [
            'voucher_type'    => 'PAYMENT',
            'partner_side'    => 'credit',   // Vendor payable cleared
            'base_side'       => 'debit',    // Bank/cash goes out
            'tax_side'        => null,        // No tax on payments
            'partner_setting' => 'credit_ledger',
            'base_setting'    => 'bank_account',
            'module'          => 'Payment',
        ],
        'receipt' => [
            'voucher_type'    => 'RECEIPT',
            'partner_side'    => 'credit',   // Customer AR cleared
            'base_side'       => 'debit',    // Bank/cash comes in
            'tax_side'        => null,        // No tax on receipts
            'partner_setting' => 'debit_ledger',
            'base_setting'    => 'bank_account',
            'module'          => 'Receipt',
        ],
        'credit_note' => [
            'voucher_type'    => 'CREDIT_NOTE',
            'partner_side'    => 'credit',   // Customer AR reduced/cleared
            'base_side'       => 'debit',    // Revenue reduced/debited
            'tax_side'        => 'debit',    // Output GST reduced/debited
            'partner_setting' => 'debit_ledger',
            'base_setting'    => 'sales_account',
            'module'          => 'Invoice',
        ],
    ];

    /**
     * @throws AccountingException
     */
    public static function get(string $docType): array
    {
        $type = strtolower($docType);
        if (!isset(self::CONFIG[$type])) {
            throw new AccountingException("Unsupported document type: '{$type}'. Supported: "
                . implode(', ', array_keys(self::CONFIG)));
        }
        return self::CONFIG[$type];
    }
}
