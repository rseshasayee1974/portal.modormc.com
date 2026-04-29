<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use App\Models\Quotation;
use App\Models\Plant;
use App\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'quotation_id' => Quotation::exists() ? Quotation::inRandomOrder()->first()->id : null,
            'patron_id' => Patron::exists() ? Patron::inRandomOrder()->first()->id : 1,
            'site_id' => 1,
            'order_date' => now(),
            'status' => SalesOrder::STATUS_CONFIRMED,
        ];
    }
}
