<?php

namespace Database\Seeders;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class QuotationSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::first();
        if (!$product) {
            $product = Product::factory()->create();
        }

        Quotation::factory(5)->create()->each(function ($quote) use ($product) {
            $item = new QuotationItem([
                'product_id' => $product->id,
                'quantity' => rand(10, 50),
                'rate' => rand(100, 500)
            ]);
            $quote->items()->save($item);
            $quote->updateTotals();
        });
    }
}
