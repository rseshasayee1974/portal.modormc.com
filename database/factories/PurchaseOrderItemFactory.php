<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Tax;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::inRandomOrder()->first();
        $qty = $this->faker->numberBetween(1, 100);
        $price = $this->faker->randomFloat(2, 5, 200);
        $tax = Tax::inRandomOrder()->first();
        
        $subtotal = $qty * $price;
        $taxRate = $tax ? $tax->tax_rate : 0;
        $taxAmount = ($subtotal * $taxRate) / 100;
        $total = $subtotal + $taxAmount;

        return [
            'plant_id' => 1,
            'order_id' => PurchaseOrder::factory(),
            'product_id' => $product ? $product->id : 1,
            'product_uom' => $product ? $product->unit_id : 1,
            'tax_id' => $tax ? $tax->id : null,
            'product_quantity' => $qty,
            'unit_price' => $price,
            'price_subtotal' => $subtotal,
            'price_tax' => $taxAmount,
            'price_total' => $total,
            'status' => 1,
            'created_by' => User::inRandomOrder()->first()?->id ?? 1,
        ];
    }
}
