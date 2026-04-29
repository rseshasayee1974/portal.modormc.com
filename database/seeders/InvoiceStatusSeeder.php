<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceStatus;

class InvoiceStatusSeeder extends Seeder
{
    public function run(): void
    {
        InvoiceStatus::factory()->count(10)->create();
    }
}