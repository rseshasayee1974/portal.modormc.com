<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use App\Models\Expense;
use App\Models\PettyCash;
use App\Models\Ledger;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Seeder;

class PettyCashSeeder extends Seeder
{
    public function run(): void
    {
        $plant = Plant::first();
        if (!$plant) return;

        $ledger = Ledger::first();
        if (!$ledger) return;

        $user = User::first();

        // Create expense types
        $types = ExpenseType::factory(4)->create(['plant_id' => $plant->id]);

        // Create expenses
        $expenses = Expense::factory(6)->create([
            'plant_id'        => $plant->id,
            'expense_type_id' => $types->first()->id,
            'paid_through'    => $ledger->id,
            'made_by'         => $user?->id,
        ]);

        // Create petty cash registers with items
        PettyCash::factory(3)->create([
            'plant_id' => $plant->id,
            'paid_by'  => $user?->id,
        ])->each(function ($pc) use ($expenses, $plant) {
            foreach ($expenses->take(2) as $expense) {
                $pc->items()->create([
                    'plant_id'   => $plant->id,
                    'expense_id' => $expense->id,
                    'amount'     => $expense->amount,
                    'debit'      => $expense->amount,
                    'credit'     => 0,
                    'date'       => now(),
                    'description'=> 'Seeded transaction',
                ]);
            }
            $pc->recalculate();
        });
    }
}
