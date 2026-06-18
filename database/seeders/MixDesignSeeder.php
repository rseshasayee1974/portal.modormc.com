<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\ConcreteGrade;

class MixDesignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entityId = Entity::first()?->id ?? 1;
        $partnerId = Patron::first()?->id ?? 1;
        $plantId = Plant::first()?->id ?? 2;

        $kgUnitId = 20;

        $catId = ProductCategory::first()?->id ?? 1;

        /*
        |--------------------------------------------------------------------------
        | Concrete Grade (M25)
        |--------------------------------------------------------------------------
        */
        $grade = ConcreteGrade::firstOrCreate(
            [
                'plant_id' => $plantId,
                'name' => 'M25',
            ],
            [
                'concrete_code' => 'M25',
                'concrete_ratio' => '1:1:2',
                'cement_ratio' => 1,
                'sand_ratio' => 1,
                'aggregate_ratio' => 2,
                'status' => 1,
                'created_by' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */
        $products = [
            'Cement OPC 43' => [
                'rate' => 7.50,
                'code' => 'CMT-01',
            ],
            'Fine Sand' => [
                'rate' => 1.20,
                'code' => 'SND-01',
            ],
            'Coarse Aggregate 10mm' => [
                'rate' => 1.80,
                'code' => 'AGG-10',
            ],
            'Coarse Aggregate 20mm' => [
                'rate' => 1.90,
                'code' => 'AGG-20',
            ],
            'Water' => [
                'rate' => 0.10,
                'code' => 'WTR-01',
            ],
            'Admixture (Superplasticizer)' => [
                'rate' => 85.00,
                'code' => 'ADM-01',
            ],
        ];

        $productIds = [];

        foreach ($products as $name => $data) {

            $product = Product::updateOrCreate(
                [
                    'plant_id' => $plantId,
                    'title' => $name,
                ],
                [
                    'category_id' => $catId,
                    'unit_id' => $kgUnitId,
                    'purchase_price' => $data['rate'],
                    'sales_price' => round($data['rate'] * 1.2, 2),
                    'code' => $data['code'],
                    'status' => 1,
                    'created_by' => 1,
                ]
            );

            $productIds[$name] = $product->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Mix Design
        |--------------------------------------------------------------------------
        */
        $mixDesign = MixDesign::updateOrCreate(
            [
                'plant_id' => $plantId,
                'design_code' => 'RMC-M25-001',
            ],
            [
                'partner_id' => $partnerId,
                'concrete_grade_id' => $grade->id,
                'design_name' => 'Standard M25 - Grade RMC',
                'design_type' => 'M25',
                'unit_id' => $kgUnitId,
                'rate_per_qty' => 4500,
                'created_by' => 1,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Mix Design Ingredients
        |--------------------------------------------------------------------------
        */
        $ingredients = [
            [
                'name' => 'Cement OPC 43',
                'qty' => 380,
            ],
            [
                'name' => 'Fine Sand',
                'qty' => 710,
            ],
            [
                'name' => 'Coarse Aggregate 20mm',
                'qty' => 750,
            ],
            [
                'name' => 'Coarse Aggregate 10mm',
                'qty' => 480,
            ],
            [
                'name' => 'Water',
                'qty' => 180,
            ],
            [
                'name' => 'Admixture (Superplasticizer)',
                'qty' => 3.5,
            ],
        ];

        foreach ($ingredients as $ingredient) {

            MixDesignItem::updateOrCreate(
                [
                    'mix_design_id' => $mixDesign->id,
                    'product_id' => $productIds[$ingredient['name']],
                ],
                [
                    'plant_id' => $plantId,
                    'uom_id' => $kgUnitId,
                    'actual_quantity' => $ingredient['qty'],
                    'rate' => $products[$ingredient['name']]['rate'],
                    'created_by' => 1,
                ]
            );
        }

        $this->command->info('M25 Mix Design seeded successfully.');
    }
}