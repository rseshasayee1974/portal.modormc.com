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

            $plantId  = $plant->id;
            $entityId = $plant->entity_id;

            $ledgerSchema = [

                // ASSETS
                'Cash in Hand'       => ['type' => 'Current Assets', 'code' => '1101', 'is_pnl' => false],
                'Main Bank Account'  => ['type' => 'Current Assets', 'code' => '1102', 'is_pnl' => false],
                'Petty Cash'         => ['type' => 'Current Assets', 'code' => '1103', 'is_pnl' => false],
                'Sundry Debtors'     => ['type' => 'Current Assets', 'code' => '1104', 'is_pnl' => false],
                'Closing Stock'      => ['type' => 'Current Assets', 'code' => '1105', 'is_pnl' => false],

                'Plant & Machinery'  => ['type' => 'Fixed Assets', 'code' => '1201', 'is_pnl' => false],
                'Office Equipments'  => ['type' => 'Fixed Assets', 'code' => '1202', 'is_pnl' => false],
                'Vehicles'           => ['type' => 'Fixed Assets', 'code' => '1203', 'is_pnl' => false],

                // LIABILITIES
                'Sundry Creditors'     => ['type' => 'Current Liabilities', 'code' => '2101', 'is_pnl' => false],
                'Outstanding Salaries' => ['type' => 'Current Liabilities', 'code' => '2102', 'is_pnl' => false],

                'Input CGST'  => ['type' => 'Duties & Taxes', 'code' => '2401', 'is_pnl' => false],
                'Input SGST'  => ['type' => 'Duties & Taxes', 'code' => '2402', 'is_pnl' => false],
                'Input IGST'  => ['type' => 'Duties & Taxes', 'code' => '2403', 'is_pnl' => false],
                'Output CGST' => ['type' => 'Duties & Taxes', 'code' => '2404', 'is_pnl' => false],
                'Output SGST' => ['type' => 'Duties & Taxes', 'code' => '2405', 'is_pnl' => false],
                'Output IGST' => ['type' => 'Duties & Taxes', 'code' => '2406', 'is_pnl' => false],
                'TDS Payable' => ['type' => 'Duties & Taxes', 'code' => '2407', 'is_pnl' => false],
                'TDS Receivable' => ['type' => 'Duties & Taxes', 'code' => '2408', 'is_pnl' => false],

                // EQUITY
                'Capital Account' => ['type' => 'Capital Account', 'code' => '3101', 'is_pnl' => false],

                // REVENUE
                'Sales Account'  => ['type' => 'Sales Accounts', 'code' => '4101', 'is_pnl' => true],
                'Service Income' => ['type' => 'Sales Accounts', 'code' => '4102', 'is_pnl' => true],

                // PURCHASES
                'Purchase Account' => ['type' => 'Purchase Accounts', 'code' => '5101', 'is_pnl' => true],
                'Direct Materials' => ['type' => 'Purchase Accounts', 'code' => '5102', 'is_pnl' => true],

                // DIRECT EXPENSES
                'Freight & Forwarding' => ['type' => 'Direct Expenses', 'code' => '5201', 'is_pnl' => true],
                'Wages'                => ['type' => 'Direct Expenses', 'code' => '5202', 'is_pnl' => true],
                'Power & Fuel'         => ['type' => 'Direct Expenses', 'code' => '5203', 'is_pnl' => true],

                // INDIRECT EXPENSES
                'Staff Salaries'       => ['type' => 'Indirect Expenses', 'code' => '5301', 'is_pnl' => true],
                'Office Rent'          => ['type' => 'Indirect Expenses', 'code' => '5302', 'is_pnl' => true],
                'Electricity Charges'  => ['type' => 'Indirect Expenses', 'code' => '5303', 'is_pnl' => true],
                'Telephone & Internet' => ['type' => 'Indirect Expenses', 'code' => '5304', 'is_pnl' => true],
                'Printing & Stationary'=> ['type' => 'Indirect Expenses', 'code' => '5305', 'is_pnl' => true],
                'Repair & Maintenance' => ['type' => 'Indirect Expenses', 'code' => '5306', 'is_pnl' => true],
                'Round Off Account'    => ['type' => 'Indirect Expenses', 'code' => '5307', 'is_pnl' => true],
                'Adjustment Account'   => ['type' => 'Indirect Expenses', 'code' => '5308', 'is_pnl' => true],
                'Bank Charges'         => ['type' => 'Indirect Expenses', 'code' => '5309', 'is_pnl' => true],
                'Discount Allowed'     => ['type' => 'Indirect Expenses', 'code' => '5310', 'is_pnl' => true],
            ];

            foreach ($ledgerSchema as $title => $data) {

                $accountType = AccountsType::where('entity_id', $entityId)
                    ->where('plant_id', $plantId)
                    ->where('title', $data['type'])
                    ->first();

                if (!$accountType) {
                    $this->command->warn(
                        "Account Type '{$data['type']}' not found for Plant {$plantId}. Skipping '{$title}'."
                    );
                    continue;
                }

                Ledger::updateOrCreate(
                    [
                        'entity_id' => $entityId,
                        'plant_id'  => $plantId,
                        'title'     => $title,
                    ],
                    [
                        'account_type_id' => $accountType->id,
                        'code'            => $data['code'],
                        'slug'            => Str::slug($title),
                        'is_pnl'          => $data['is_pnl'],
                        'status'          => 1,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]
                );
            }

            $this->command->info("Ledgers seeded for Plant ID {$plantId}");
        }

        $this->command->info('LedgerSeeder completed successfully.');
    }
}