<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Cash', 'is_active' => true],
            ['name' => 'Bank Transfer', 'is_active' => true],
            ['name' => 'Credit Card', 'is_active' => true],
            ['name' => 'UPI', 'is_active' => true],
            ['name' => 'Cheque', 'is_active' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['name' => $method['name']],
                $method
            );
        }
    }
}
