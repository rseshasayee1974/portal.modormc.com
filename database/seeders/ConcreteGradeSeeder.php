<?php

namespace Database\Seeders;

use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\Product;
use App\Models\Entity;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ConcreteGradeSeeder extends Seeder
{
    public function run(): void
    {
        $entityId = Entity::first()->id ?? 1;
        $kgUnitId = ProductUnit::where('unit_name', 'KG')->first()->id ?? 1;
        $catId = ProductCategory::first()->id ?? 1;

        // Ensure key RMC products exist (Cement, Sand, Aggregates)
        $requiredProducts = [
            'Cement OPC 53 Grade' => 'CMT-001',
            'Crushed Sand (M-Sand)' => 'SND-001',
            'Coarse Aggregate 10mm' => 'AGG-010',
            'Coarse Aggregate 20mm' => 'AGG-020',
        ];

        $productIds = [];
        foreach ($requiredProducts as $name => $code) {
            $product = Product::firstOrCreate(
                ['entity_id' => $entityId, 'title' => $name],
                [
                    'category_id' => $catId,
                    'unit_id' => $kgUnitId,
                    'code' => $code,
                    'purchase_price' => 5,
                    'sales_price' => 6,
                    'status' => 1
                ]
            );
            $productIds[$name] = $product->id;
        }

        // Live M20 RMC Mix Design (Approx 1:1.5:3)
        $m20 = ConcreteGrade::create([
            'entity_id' => $entityId,
            'name' => 'M20',
            'concrete_code' => 'STD-M20',
            'concrete_ratio' => '1:1.5:3',
            'cement_ratio' => 1.0,
            'sand_ratio' => 1.5,
            'aggregate_ratio' => 3.0,
            'status' => 1,
            'created_by' => 1,
        ]);

        $m20Items = [
            ['name' => 'Cement OPC 53 Grade', 'qty' => 320],
            ['name' => 'Crushed Sand (M-Sand)', 'qty' => 700],
            ['name' => 'Coarse Aggregate 20mm', 'qty' => 1100],
        ];

        foreach ($m20Items as $item) {
            ConcreteGradeItem::create([
                'entity_id' => $entityId,
                'concrete_grade_id' => $m20->id,
                'product_id' => $productIds[$item['name']],
                'quantity' => $item['qty'],
                'created_by' => 1,
            ]);
        }

        // Live M25 RMC Mix Design (Approx 1:1:2)
        $m25 = ConcreteGrade::create([
            'entity_id' => $entityId,
            'name' => 'M25',
            'concrete_code' => 'STD-M25',
            'concrete_ratio' => '1:1:2',
            'cement_ratio' => 1.0,
            'sand_ratio' => 1.0,
            'aggregate_ratio' => 2.0,
            'status' => 1,
            'created_by' => 1,
        ]);

        $m25Items = [
            ['name' => 'Cement OPC 53 Grade', 'qty' => 380],
            ['name' => 'Crushed Sand (M-Sand)', 'qty' => 700],
            ['name' => 'Coarse Aggregate 20mm', 'qty' => 1100],
        ];

        foreach ($m25Items as $item) {
            ConcreteGradeItem::create([
                'entity_id' => $entityId,
                'concrete_grade_id' => $m25->id,
                'product_id' => $productIds[$item['name']],
                'quantity' => $item['qty'],
                'created_by' => 1,
            ]);
        }
    }
}
