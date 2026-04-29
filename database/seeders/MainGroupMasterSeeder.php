<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MainGroupMaster;

class MainGroupMasterSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'group_name'  => 'Assets',
                'group_code'  => 'ASSETS',
                'description' => 'Resources owned by the business that have economic value (e.g., cash, inventory, property).',
                'status'      => 1,
            ],
            [
                'group_name'  => 'Liabilities',
                'group_code'  => 'LIAB',
                'description' => 'Obligations or debts owed by the business to external parties (e.g., loans, creditors).',
                'status'      => 1,
            ],
            [
                'group_name'  => 'Equity',
                'group_code'  => 'EQUITY',
                'description' => 'Owner\'s interest in the business; assets minus liabilities (e.g., capital, retained earnings).',
                'status'      => 1,
            ],
            [
                'group_name'  => 'Income',
                'group_code'  => 'INCOME',
                'description' => 'Revenue earned by the business from its primary and secondary operations (e.g., sales, interest income).',
                'status'      => 1,
            ],
            [
                'group_name'  => 'Expenses',
                'group_code'  => 'EXP',
                'description' => 'Costs incurred by the business in the course of generating revenue (e.g., salaries, rent, utilities).',
                'status'      => 1,
            ],
        ];

        foreach ($groups as $group) {
            MainGroupMaster::updateOrCreate(
                ['group_code' => $group['group_code']],
                $group
            );
        }
    }
}
