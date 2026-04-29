<?php

namespace Database\Seeders;

use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Patron;
use App\Models\Entity;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class MixDesignSeeder extends Seeder
{
    public function run(): void
    {
        $entityId = Entity::first()->id ?? 1;
        $partnerId = Patron::first()->id ?? 1;
        $kgUnitId = ProductUnit::where('unit_name', 'KG')->first()->id ?? ProductUnit::first()->id ?? 1;
        $catId = ProductCategory::first()->id ?? 1;

        // Ensure key RMC products exist
        $products = [
            'Cement OPC 43' => ['rate' => 7.5, 'code' => 'CMT-01'],
            'Fine Sand' => ['rate' => 1.2, 'code' => 'SND-01'],
            'Coarse Aggregate 10mm' => ['rate' => 1.8, 'code' => 'AGG-10'],
            'Coarse Aggregate 20mm' => ['rate' => 1.9, 'code' => 'AGG-20'],
            'Water' => ['rate' => 0.1, 'code' => 'WTR-01'],
            'Admixture (Superplasticizer)' => ['rate' => 85, 'code' => 'ADM-01'],
        ];

        $productIds = [];
        foreach ($products as $name => $data) {
            $product = Product::firstOrCreate(
                ['entity_id' => $entityId, 'title' => $name],
                [
                    'category_id' => $catId,
                    'unit_id' => $kgUnitId,
                    'purchase_price' => $data['rate'],
                    'sales_price' => $data['rate'] * 1.2,
                    'code' => $data['code'],
                    'status' => 1
                ]
            );
            $productIds[$name] = $product->id;
        }

        // Create a Standard M25 Mix Design
        $m25 = MixDesign::create([
            'entity_id' => $entityId,
            'partner_id' => $partnerId,
            'design_name' => 'Standard M25 - Grade RMC',
            'design_code' => 'RMC-M25-001',
            'design_type' => 'M25',
            'unit' => 'm3',
            'rate_per_qty' => 4500,
            'created_by' => 1,
        ]);

        // Ingredients for 1m3 of M25 (Approx IS 10262)
        $ingredients = [
            ['name' => 'Cement OPC 43', 'qty' => 380],
            ['name' => 'Fine Sand', 'qty' => 710],
            ['name' => 'Coarse Aggregate 20mm', 'qty' => 750],
            ['name' => 'Coarse Aggregate 10mm', 'qty' => 480],
            ['name' => 'Water', 'qty' => 180],
            ['name' => 'Admixture (Superplasticizer)', 'qty' => 3.5],
        ];

        foreach ($ingredients as $ing) {
            MixDesignItem::create([
                'entity_id' => $entityId,
                'mix_design_id' => $m25->id,
                'product_id' => $productIds[$ing['name']],
                'uom_id' => $kgUnitId,
                'actual_quantity' => $ing['qty'],
                'rate' => $products[$ing['name']]['rate'],
                'created_by' => 1,
            ]);
        }
    }
}
