<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $qty       = $this->faker->randomFloat(2, 1, 100);
        $price     = $this->faker->randomFloat(2, 100, 5000);
        $discount  = $this->faker->randomFloat(2, 0, 10);
        $subtotal  = round($qty * $price * (1 - $discount / 100), 2);
        $taxAmount = round($subtotal * 0.18, 2);

        return [
            'invoice_id'      => Invoice::exists() ? Invoice::inRandomOrder()->first()->id : 1,
            'item_name'       => $this->faker->randomElement(['M25 Concrete', 'M30 Concrete', 'River Sand', 'Aggregate 20mm']),
            'hsn_code'        => $this->faker->numerify('6810##'),
            'quantity'        => $qty,
            'price_unit'      => $price,
            'discount_type'   => '%',
            'discount'        => $discount,
            'discount_amount' => round($qty * $price * $discount / 100, 2),
            'subtotal'        => $subtotal,
            'line_tax_amount' => $taxAmount,
            'line_total'      => $subtotal + $taxAmount,
        ];
    }
}
