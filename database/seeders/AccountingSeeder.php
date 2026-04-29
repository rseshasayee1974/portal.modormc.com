<?php

namespace Database\Seeders;

use App\Models\Accounts;
use App\Models\AccountsType;
use App\Models\Ledger;
use App\Models\Plant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AccountingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plant = Plant::first();
        if (!$plant) {
            $this->command->info('No plant found. Skipping accounting seed.');
            return;
        }

        $plantId = $plant->id;

        // Structured hierarchy for Tally/ERP standards
        $schema = [
            'ASSET' => [
                'code' => '1000',
                'subgroups' => [
                    'Current Assets' => [
                        'code' => '1100',
                        'ledgers' => [
                            ['code' => '1101', 'title' => 'Cash in Hand', 'is_pnl' => false],
                            ['code' => '1102', 'title' => 'Bank Accounts', 'is_pnl' => false],
                            ['code' => '1103', 'title' => 'Accounts Receivable', 'is_pnl' => false],
                            ['code' => '1104', 'title' => 'Inventory/Stock', 'is_pnl' => false],
                        ]
                    ],
                    'Fixed Assets' => [
                        'code' => '1200',
                        'ledgers' => [
                            ['code' => '1201', 'title' => 'Plant & Machinery', 'is_pnl' => false],
                            ['code' => '1202', 'title' => 'Land & Buildings', 'is_pnl' => false],
                            ['code' => '1203', 'title' => 'Office Equipment', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'LIABILITY' => [
                'code' => '2000',
                'subgroups' => [
                    'Current Liabilities' => [
                        'code' => '2100',
                        'ledgers' => [
                            ['code' => '2101', 'title' => 'Accounts Payable', 'is_pnl' => false],
                            ['code' => '2103', 'title' => 'Outstanding Expenses', 'is_pnl' => false],
                        ]
                    ],
                    'Duties & Taxes' => [
                        'code' => '2400',
                        'ledgers' => [
                            ['code' => '2401', 'title' => 'Payable CGST', 'is_pnl' => false],
                            ['code' => '2402', 'title' => 'Payable SGST', 'is_pnl' => false],
                            ['code' => '2403', 'title' => 'Payable IGST', 'is_pnl' => false],
                            ['code' => '2404', 'title' => 'Receivable CGST', 'is_pnl' => false],
                            ['code' => '2405', 'title' => 'Receivable SGST', 'is_pnl' => false],
                            ['code' => '2406', 'title' => 'Receivable IGST', 'is_pnl' => false],
                            ['code' => '2407', 'title' => 'TDS Payable', 'is_pnl' => false],
                            ['code' => '2408', 'title' => 'TDS Receivable', 'is_pnl' => false],
                        ]
                    ],
                    'Loans (Liability)' => [
                        'code' => '2200',
                        'ledgers' => [
                            ['code' => '2201', 'title' => 'Bank Loans', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'EQUITY' => [
                'code' => '3000',
                'subgroups' => [
                    'Capital Account' => [
                        'code' => '3100',
                        'ledgers' => [
                            ['code' => '3101', 'title' => 'Partner Capital A/c', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'REVENUE' => [
                'code' => '4000',
                'subgroups' => [
                    'Sales Accounts' => [
                        'code' => '4100',
                        'ledgers' => [
                            ['code' => '4101', 'title' => 'Domestic Sales', 'is_pnl' => true],
                            ['code' => '4102', 'title' => 'Export Sales', 'is_pnl' => true],
                        ]
                    ],
                    'Indirect Income' => [
                        'code' => '4300',
                        'ledgers' => [
                            ['code' => '4301', 'title' => 'Interest Received', 'is_pnl' => true],
                        ]
                    ],
                ]
            ],
            'EXPENSE' => [
                'code' => '5000',
                'subgroups' => [
                    'Purchase Accounts' => [
                        'code' => '5100',
                        'ledgers' => [
                            ['code' => '5101', 'title' => 'Raw Material Purchases', 'is_pnl' => true],
                        ]
                    ],
                    'Indirect Expenses' => [
                        'code' => '5300',
                        'ledgers' => [
                            ['code' => '5301', 'title' => 'Staff Salaries', 'is_pnl' => true],
                            ['code' => '5302', 'title' => 'Office Rent', 'is_pnl' => true],
                            ['code' => '5303', 'title' => 'Electricity & Power', 'is_pnl' => true],
                        ]
                    ],
                ]
            ],
        ];

        foreach ($schema as $groupTitle => $groupData) {
            $account = Accounts::updateOrCreate(
                ['code' => $groupData['code'], 'plant_id' => $plantId],
                [
                    'plant_id'  => $plantId,
                    'title'     => $groupTitle,
                    'status'    => 1,
                    'created'   => now(),
                ]
            );

            foreach ($groupData['subgroups'] as $subGroupTitle => $subGroupData) {
                $accountType = AccountsType::updateOrCreate(
                    ['code' => $subGroupData['code'], 'plant_id' => $plantId],
                    [
                        'account_id' => $account->id,
                        'plant_id'   => $plantId,
                        'title'      => $subGroupTitle,
                        'status'     => 1,
                        'created_at' => now(),
                    ]
                );

                foreach ($subGroupData['ledgers'] as $ledgerData) {
                    Ledger::updateOrCreate(
                        ['code' => $ledgerData['code'], 'plant_id' => $plantId],
                        [
                            'account_type_id' => $accountType->id,
                            'plant_id'        => $plantId,
                            'title'           => $ledgerData['title'],
                            'slug'            => Str::slug($ledgerData['title']),
                            'is_pnl'          => $ledgerData['is_pnl'],
                            'status'          => 1,
                            'created_at'      => now(),
                        ]
                    );
                }
            }
        }
    }
}
