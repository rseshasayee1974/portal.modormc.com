<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BankAccountType;

class BankAccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        BankAccountType::factory()->count(10)->create();
    }
}