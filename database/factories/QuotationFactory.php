<?php

namespace Database\Factories;

use App\Models\Patron;
use App\Models\Plant;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::exists() ? Plant::inRandomOrder()->first()->id : 1,
            'reference' => 'QT-' . $this->faker->unique()->numberBetween(1000, 9999),
            'patron_id' => Patron::exists() ? Patron::inRandomOrder()->first()->id : 1,
            'quote_date' => now(),
            'validity_date' => now()->addDays(30),
            'amount_untaxed' => 1000,
            'amount_tax' => 180,
            'amount_total' => 1180,
            'status' => Quotation::STATUS_DRAFT,
        ];
    }
}
