<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MachineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entities = \App\Models\Entity::all();
        if ($entities->isEmpty()) return;

        foreach ($entities as $entity) {
            \App\Models\Machine::factory(5)->create([
                'plant_id' => $entity->plants->first()?->id ?? 1,
            ])->each(function ($machine) {
                // Documents
                $machine->documents()->createMany([
                    ['type' => 'insurance', 'issue_date' => now()->subMonths(6), 'expiry_date' => now()->addMonths(6), 'amount' => 15000],
                    ['type' => 'fc', 'issue_date' => now()->subMonths(10), 'expiry_date' => now()->addMonths(2), 'amount' => 5000],
                ]);

                // Loans
                $loan = $machine->loans()->create([
                    'loan_amount' => 2000000,
                    'emi_amount' => 45000,
                    'tenure_months' => 48,
                    'start_date' => now()->subMonths(12),
                    'end_date' => now()->addMonths(36),
                ]);

                // EMIs
                for ($i = 1; $i <= 12; $i++) {
                    $loan->emiPayments()->create([
                        'due_date' => now()->subMonths($i)->startOfMonth(),
                        'paid_date' => now()->subMonths($i)->addDays(5),
                        'amount' => 45000,
                        'status' => 'paid',
                    ]);
                }
            });
        }
    }
}
