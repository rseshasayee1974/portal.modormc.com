<?php

namespace Database\Factories;

use App\Models\CustomerPO;
use App\Models\Quotation;
use App\Models\Plant;
use App\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerPOFactory extends Factory
{
    protected $model = CustomerPO::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'quotation_id' => Quotation::exists() ? Quotation::inRandomOrder()->first()->id : null,
            'patron_id' => Patron::exists() ? Patron::inRandomOrder()->first()->id : 1,
            'site_id' => 1,
            'order_date' => now(),
            'status' => CustomerPO::STATUS_CONFIRMED,
        ];
    }
}
