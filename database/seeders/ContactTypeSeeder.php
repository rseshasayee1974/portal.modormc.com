<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ContactType;

class ContactTypeSeeder extends Seeder
{
    public function run(): void
    {
        ContactType::factory()->count(10)->create();
    }
}