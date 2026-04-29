<?php

namespace Database\Seeders;

use App\Models\Accounts;
use App\Models\Entity;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    public function run(): void
    {
        $plants = \App\Models\Plant::all();

        if ($plants->isEmpty()) {
            Accounts::factory()->count(10)->create();
            return;
        }

        foreach ($plants as $plant) {
            foreach (Accounts::accountNameType() as $type) {
                Accounts::firstOrCreate(
                    [
                        'plant_id' => $plant->id,
                        'title'     => $type,
                    ],
                    [
                        'status'  => 1,
                        'created' => now(),
                    ]
                );
            }
        }
    }
}
