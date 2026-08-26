<?php

namespace Database\Seeders;

use App\Models\AccountDefaultSetting;
use App\Models\Ledger;
use App\Models\Module;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class AccountDefaultSettingSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();

        foreach ($plants as $plant) {
            $this->seedDefaultsForPlant($plant);
        }
    }

    private function seedDefaultsForPlant($plant)
    {
        $mappings = [
            'Invoice' => [
                'sales_account'     => 'Sales Account',
                'cgst_output'       => 'Output CGST',
                'sgst_output'       => 'Output SGST',
                'igst_output'       => 'Output IGST',
                'shipping_account'  => 'Freight & Forwarding',
                'round_off_account' => 'Round Off Account',
                'tax_account'       => 'Output CGST',
                'adjustment_account'=> 'Adjustment Account',
                'tds_receivable'    => 'TDS Receivable',
            ],
            'Purchase' => [
                'purchase_account'  => 'Purchase Expense Account',
                'cgst_input'        => 'Input CGST',
                'sgst_input'        => 'Input SGST',
                'igst_input'        => 'Input IGST',
                'shipping_account'  => 'Freight & Forwarding',
                'round_off_account' => 'Round Off Account',
                'tax_account'       => 'Input CGST',
                'tds_payable'       => 'TDS Payable',
            ],
            'Payment' => [
                'cash_account'      => 'Cash in Hand',
                'bank_account'      => 'Main Bank Account',
                'discount_allowed'  => 'Discount Allowed',
            ],
            'Receipt' => [
                'cash_account'      => 'Cash in Hand',
                'bank_account'      => 'Main Bank Account',
                'discount_received' => 'Indirect Income', // Or add a Discount Received ledger
            ],
            'Patron' => [
                'debit_ledger'      => 'Sundry Debtors',
                'credit_ledger'     => 'Sundry Creditors',
            ],
        ];

        foreach ($mappings as $moduleName => $keys) {
            $module = Module::where('module_name', $moduleName)->first();
            if (!$module) continue;

            foreach ($keys as $key => $ledgerTitle) {
                $ledger = Ledger::where('plant_id', $plant->id)
                    ->where('title', 'like', "%{$ledgerTitle}%")
                    ->first();

                if ($ledger) {
                    AccountDefaultSetting::updateOrCreate(
                        [
                            'plant_id'    => $plant->id,
                            'module_id'   => $module->id,
                            'setting_key' => $key,
                        ],
                        [
                            'module_name' => $module->module_name,
                            'ledger_id'   => $ledger->id,
                            'is_active'   => true,
                        ]
                    );
                }
            }
        }
    }
}
