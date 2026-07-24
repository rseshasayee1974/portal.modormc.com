<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AddressType;

class AddressTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (AddressType::count() === 0) {
            $addressTypes = [
                'Billing',
                'Shipping',
                'Head Office',
                'Plant Site',
                'Registered Office',
                'Factory',
                'Warehouse',
                'Godown',
                'Plant Office',
                'RMC Plant',
                'Quarry Site'
            ];

            foreach ($addressTypes as $type) {
                AddressType::create(['type' => $type]);
            }
        }
    }
}