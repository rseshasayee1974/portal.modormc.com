<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityBankAccount;

class EntityBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        EntityBankAccount::factory()->count(10)->create();
    }
}