<?php

namespace Database\Seeders;

use App\Models\AccountsType;
use App\Models\Ledger;
use App\Models\Plant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LedgerSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();

        if ($plants->isEmpty()) {
            $this->command->info('No plants found. Skipping ledger seeding.');
            return;
        }

        foreach ($plants as $plant) {
            $plantId = $plant->id;
            $entityId = $plant->entity_id;

            // Standard Ledgers mapping to Account Types
            $ledgerSchema = [
                'Cash in Hand'          => ['type' => 'Current Assets', 'code' => '1101', 'is_pnl' => false],
                'Bank Accounts'         => ['type' => 'Current Assets', 'code' => '1102', 'is_pnl' => false],
                'Accounts Receivable'   => ['type' => 'Current Assets', 'code' => '1103', 'is_pnl' => false],
                'Inventory/Stock'       => ['type' => 'Current Assets', 'code' => '1104', 'is_pnl' => false],
                
                'Plant & Machinery'     => ['type' => 'Fixed Assets', 'code' => '1201', 'is_pnl' => false],
                'Land & Buildings'      => ['type' => 'Fixed Assets', 'code' => '1202', 'is_pnl' => false],
                
                'Accounts Payable'      => ['type' => 'Current Liabilities', 'code' => '2101', 'is_pnl' => false],
                'Outstanding Expenses'  => ['type' => 'Current Liabilities', 'code' => '2102', 'is_pnl' => false],
                
                'CGST Payable'          => ['type' => 'Duties & Taxes', 'code' => '2401', 'is_pnl' => false],
                'SGST Payable'          => ['type' => 'Duties & Taxes', 'code' => '2402', 'is_pnl' => false],
                'IGST Payable'          => ['type' => 'Duties & Taxes', 'code' => '2403', 'is_pnl' => false],
                'TDS Payable'           => ['type' => 'Duties & Taxes', 'code' => '2404', 'is_pnl' => false],
                
                'Partner Capital A/c'   => ['type' => 'Capital Account', 'code' => '3101', 'is_pnl' => false],
                
                'Domestic Sales'        => ['type' => 'Sales Accounts', 'code' => '4101', 'is_pnl' => true],
                'Export Sales'          => ['type' => 'Sales Accounts', 'code' => '4102', 'is_pnl' => true],
                
                'Raw Material Purchases'=> ['type' => 'Purchase Accounts', 'code' => '5101', 'is_pnl' => true],
                
                'Staff Salaries'        => ['type' => 'Indirect Expenses', 'code' => '5301', 'is_pnl' => true],
                'Office Rent'           => ['type' => 'Indirect Expenses', 'code' => '5302', 'is_pnl' => true],
                'Electricity & Power'   => ['type' => 'Indirect Expenses', 'code' => '5303', 'is_pnl' => true],
                'Internet & Phone'      => ['type' => 'Indirect Expenses', 'code' => '5304', 'is_pnl' => true],
            ];

            foreach ($ledgerSchema as $title => $data) {
                $accountType = AccountsType::where('plant_id', $plantId)
                    ->where('title', $data['type'])
                    ->first();

                if ($accountType) {
                    Ledger::updateOrCreate(
                        ['plant_id' => $plantId, 'title' => $title],
                        [
                            'account_type_id' => $accountType->id,
                            'code'            => $data['code'],
                            'slug'            => Str::slug($title),
                            'is_pnl'          => $data['is_pnl'],
                            'status'          => 1,
                            'created_at'      => now(),
                        ]
                    );
                }
            }
        }
    }
}
