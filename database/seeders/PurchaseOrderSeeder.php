<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Tax;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Currency::count() === 0) {
            Currency::create(['currency_name' => 'Indian Rupee', 'currency_code' => 'INR']);
            Currency::create(['currency_name' => 'US Dollar', 'currency_code' => 'USD']);
        }

        // Ensure there are some basic records first (optional)
        if (PurchaseOrder::count() > 0) return;

        PurchaseOrder::factory()
            ->count(20)
            ->has(PurchaseOrderItem::factory()->count(3), 'items')
            ->create();

        foreach (PurchaseOrder::all() as $po) {
            $po->recalculateTotals();
        }
    }
}
