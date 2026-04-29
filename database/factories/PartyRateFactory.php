<?php

namespace Database\Factories;

use App\Models\PartyRate;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Site;
use App\Models\ProductUnit;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartyRateFactory extends Factory
{
    protected $model = PartyRate::class;

    public function definition(): array
    {
        $productRate = $this->faker->randomFloat(2, 500, 2000);
        $transportRate = $this->faker->randomFloat(2, 100, 500);

        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'patron_id' => Patron::exists() ? Patron::inRandomOrder()->first()->id : null,
            'loading_site' => Site::exists() ? Site::inRandomOrder()->first()->id : null,
            'unloading_site' => Site::exists() ? Site::inRandomOrder()->first()->id : null,
            'uom_id' => ProductUnit::exists() ? ProductUnit::inRandomOrder()->first()->id : null,
            'payment_type' => $this->faker->randomElement(['Credit', 'Cash', 'Advance']),
            'product_id' => Product::exists() ? Product::inRandomOrder()->first()->id : null,
            'product_rate' => $productRate,
            'transport_rate' => $transportRate,
            'rate' => $productRate + $transportRate,
            'status' => 1,
        ];
    }
}
