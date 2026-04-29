<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VoucherType;

class VoucherTypeSeeder extends Seeder
{
    public function run(): void
    {
        $voucherTypes = [
            // GENERAL
            ['journal_name' => 'General Journal', 'short_code' => 'JV', 'is_system_generated' => true, 'prefix' => 'JV-', 'voucher_group' => 'Other'],

            // PURCHASE
            ['journal_name' => 'Purchase Journal', 'short_code' => 'PUR', 'is_system_generated' => true, 'prefix' => 'PUR-', 'voucher_group' => 'Purchase'],
            ['journal_name' => 'Vendor Bill', 'short_code' => 'VBILL', 'is_system_generated' => true, 'prefix' => 'VBILL-', 'voucher_group' => 'Purchase'],
            ['journal_name' => 'Non-Tax Vendor Bill', 'short_code' => 'VBILLNT', 'is_system_generated' => true, 'prefix' => 'VBILLNT-', 'voucher_group' => 'Purchase'],
            ['journal_name' => 'Cash Vendor Bill', 'short_code' => 'CASHVB', 'is_system_generated' => true, 'prefix' => 'CASHVB-', 'voucher_group' => 'Purchase'],
            ['journal_name' => 'Cash Non-Tax Vendor Bill', 'short_code' => 'CASHVBNT', 'is_system_generated' => true, 'prefix' => 'CASHVBNT-', 'voucher_group' => 'Purchase'],
            ['journal_name' => 'Purchase Return Journal', 'short_code' => 'PR', 'is_system_generated' => false, 'prefix' => 'PR-', 'voucher_group' => 'Purchase'],

            // SALES
            ['journal_name' => 'Sales Journal', 'short_code' => 'SALE', 'is_system_generated' => true, 'prefix' => 'SALE-', 'voucher_group' => 'Sales'],
            ['journal_name' => 'Sales Return Journal', 'short_code' => 'SR', 'is_system_generated' => false, 'prefix' => 'SR-', 'voucher_group' => 'Sales'],

            // PAYMENT / RECEIPT
            ['journal_name' => 'Payment Journal', 'short_code' => 'PAY', 'is_system_generated' => true, 'prefix' => 'PAY-', 'voucher_group' => 'Payment'],
            ['journal_name' => 'Receipt Journal', 'short_code' => 'REC', 'is_system_generated' => true, 'prefix' => 'REC-', 'voucher_group' => 'Receipt'],
            ['journal_name' => 'Contra Journal', 'short_code' => 'CON', 'is_system_generated' => false, 'prefix' => 'CON-', 'voucher_group' => 'Other'],

            // EXPENSE / ADJUSTMENT
            ['journal_name' => 'Depreciation Journal', 'short_code' => 'DEP', 'is_system_generated' => false, 'prefix' => 'DEP-', 'voucher_group' => 'Expense'],
            ['journal_name' => 'Adjustment Journal', 'short_code' => 'ADJ', 'is_system_generated' => false, 'prefix' => 'ADJ-', 'voucher_group' => 'Other'],

            // NOTES
            ['journal_name' => 'Debit Note', 'short_code' => 'DN', 'is_system_generated' => true, 'prefix' => 'DN-', 'voucher_group' => 'Debit Note'],
            ['journal_name' => 'Credit Note', 'short_code' => 'CN', 'is_system_generated' => true, 'prefix' => 'CN-', 'voucher_group' => 'Credit Note'],

            // INVOICES
            ['journal_name' => 'Tax Invoice', 'short_code' => 'TAX', 'is_system_generated' => true, 'prefix' => 'TAX-', 'voucher_group' => 'Sales'],
            ['journal_name' => 'Non-Tax Invoice', 'short_code' => 'BILL', 'is_system_generated' => true, 'prefix' => 'BILL-', 'voucher_group' => 'Sales'],
            ['journal_name' => 'Cash Tax Invoice', 'short_code' => 'CASHTAX', 'is_system_generated' => true, 'prefix' => 'CASHTAX-', 'voucher_group' => 'Sales'],
            ['journal_name' => 'Cash Non-Tax Invoice', 'short_code' => 'CASHBILL', 'is_system_generated' => true, 'prefix' => 'CASHBILL-', 'voucher_group' => 'Sales'],
        ];

        foreach ($voucherTypes as $type) {
            VoucherType::updateOrCreate(
                ['short_code' => $type['short_code']],
                $type
            );
        }
    }
}
