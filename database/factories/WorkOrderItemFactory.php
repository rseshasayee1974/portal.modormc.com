<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderItemFactory extends Factory
{
    protected $model = WorkOrderItem::class;

    public function definition(): array
    {
        return [
            'material_id' => Product::exists() ? Product::inRandomOrder()->first()->id : null,
            'required_qty' => $this->faker->randomFloat(4, 1, 50),
            'issued_qty' => 0,
            'uom_id' => ProductUnit::exists() ? ProductUnit::inRandomOrder()->first()->id : null,
            'created' => now(),
            'created_by' => 1,
        ];
    }
}
