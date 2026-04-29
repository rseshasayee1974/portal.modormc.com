<?php

namespace Database\Seeders;

use App\Models\Accounts;
use App\Models\AccountsType;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();

        if ($plants->isEmpty()) {
            $this->command->info('No plants found. Skipping account type seeding.');
            return;
        }

        foreach ($plants as $plant) {
            $plantId = $plant->id;
            $entityId = $plant->entity_id;

            // Define standard account groups (Accounts) first if they don't exist
            $accountGroups = [
                'ASSET'     => '1000',
                'LIABILITY' => '2000',
                'EQUITY'    => '3000',
                'REVENUE'   => '4000',
                'EXPENSE'   => '5000',
            ];

            $accounts = [];
            foreach ($accountGroups as $title => $code) {
                $accounts[$title] = Accounts::updateOrCreate(
                    ['plant_id' => $plantId, 'title' => $title],
                    [
                        'code'      => $code,
                        'status'    => 1,
                        'created'   => now(),
                    ]
                );
            }

            // Define standard account types (subgroups)
            $subgroups = [
                'ASSET' => [
                    ['title' => 'Current Assets', 'code' => '1100'],
                    ['title' => 'Fixed Assets', 'code' => '1200'],
                    ['title' => 'Investments', 'code' => '1300'],
                ],
                'LIABILITY' => [
                    ['title' => 'Current Liabilities', 'code' => '2100'],
                    ['title' => 'Loans (Liability)', 'code' => '2200'],
                    ['title' => 'Suspense Account', 'code' => '2300'],
                    ['title' => 'Duties & Taxes', 'code' => '2400'],
                ],
                'EQUITY' => [
                    ['title' => 'Capital Account', 'code' => '3100'],
                    ['title' => 'Reserves & Surplus', 'code' => '3200'],
                ],
                'REVENUE' => [
                    ['title' => 'Sales Accounts', 'code' => '4100'],
                    ['title' => 'Direct Income', 'code' => '4200'],
                    ['title' => 'Indirect Income', 'code' => '4300'],
                ],
                'EXPENSE' => [
                    ['title' => 'Purchase Accounts', 'code' => '5100'],
                    ['title' => 'Direct Expenses', 'code' => '5200'],
                    ['title' => 'Indirect Expenses', 'code' => '5300'],
                ],
            ];

            foreach ($subgroups as $groupTitle => $types) {
                $account = $accounts[$groupTitle];
                foreach ($types as $type) {
                    AccountsType::updateOrCreate(
                        ['plant_id' => $plantId, 'title' => $type['title']],
                        [
                            'account_id' => $account->id,
                            'code'       => $type['code'],
                            'status'     => 1,
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
