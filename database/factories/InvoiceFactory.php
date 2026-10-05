<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $plantId = Plant::exists() ? Plant::inRandomOrder()->first()->id : 1;
        $type = $this->faker->randomElement(['Invoice', 'Bill']);

        return [
            'plant_id'        => $plantId,
            'partner_id'      => Patron::exists() ? Patron::inRandomOrder()->first()->id : 1,
            'invoice_type'    => $type,
            'invoice_label'   => 'Manual',
            'invoice_number'  => 'INV-' . $this->faker->unique()->numerify('######'),
            'invoice_date'    => $this->faker->dateTimeBetween('-3 months', 'now'),
            'due_date'        => $this->faker->dateTimeBetween('now', '+30 days'),
            'subtotal'        => 1000,
            'tax_amount'      => 180,
            'total_amount'    => 1180,
            'status'          => Invoice::STATUS_DRAFT,
            'is_active'       => 1,
        ];
    }
}
