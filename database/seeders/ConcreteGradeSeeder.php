<?php

namespace Database\Seeders;

use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ConcreteGradeSeeder extends Seeder
{
    public function run(): void
    {
        $plantId = Plant::query()->value('id');

        if (!$plantId) {
            $this->command->error('No plant found. Please seed plants first.');
            return;
        }

        $kgUnitId = ProductUnit::where('unit_name', 'KG')->value('id') ?? 1;
        $catId = ProductCategory::query()->value('id') ?? 1;

        $requiredProducts = [
            'Cement OPC 53 Grade'   => 'CMT-001',
            'Crushed Sand (M-Sand)' => 'SND-001',
            'Coarse Aggregate 10mm' => 'AGG-010',
            'Coarse Aggregate 20mm' => 'AGG-020',
        ];

        $productIds = [];

        foreach ($requiredProducts as $name => $code) {
            $product = Product::firstOrCreate(
                [
                    'plant_id' => $plantId,
                    'title'    => $name,
                ],
                [
                    'category_id'    => $catId,
                    'unit_id'        => $kgUnitId,
                    'code'           => $code,
                    'purchase_price' => 5,
                    'sales_price'    => 6,
                    'status'         => true,
                    'created_by'     => 1,
                ]
            );

            $productIds[$name] = $product->id;
        }

        $grades = [
            [
                'name' => 'M15',
                'code' => 'STD-M15',
                'ratio' => '1:2:4',
                'cement_ratio' => 1,
                'sand_ratio' => 2,
                'aggregate_ratio' => 4,
                'items' => [
                    ['name' => 'Cement OPC 53 Grade', 'qty' => 300],
                    ['name' => 'Crushed Sand (M-Sand)', 'qty' => 650],
                    ['name' => 'Coarse Aggregate 10mm', 'qty' => 400],
                    ['name' => 'Coarse Aggregate 20mm', 'qty' => 800],
                ],
            ],
            [
                'name' => 'M20',
                'code' => 'STD-M20',
                'ratio' => '1:1.5:3',
                'cement_ratio' => 1,
                'sand_ratio' => 1.5,
                'aggregate_ratio' => 3,
                'items' => [
                    ['name' => 'Cement OPC 53 Grade', 'qty' => 320],
                    ['name' => 'Crushed Sand (M-Sand)', 'qty' => 700],
                    ['name' => 'Coarse Aggregate 10mm', 'qty' => 450],
                    ['name' => 'Coarse Aggregate 20mm', 'qty' => 650],
                ],
            ],
            [
                'name' => 'M25',
                'code' => 'STD-M25',
                'ratio' => '1:1:2',
                'cement_ratio' => 1,
                'sand_ratio' => 1,
                'aggregate_ratio' => 2,
                'items' => [
                    ['name' => 'Cement OPC 53 Grade', 'qty' => 380],
                    ['name' => 'Crushed Sand (M-Sand)', 'qty' => 700],
                    ['name' => 'Coarse Aggregate 10mm', 'qty' => 450],
                    ['name' => 'Coarse Aggregate 20mm', 'qty' => 650],
                ],
            ],
        ];

        foreach ($grades as $gradeData) {

            $grade = ConcreteGrade::firstOrCreate(
                [
                    'plant_id' => $plantId,
                    'name'     => $gradeData['name'],
                ],
                [
                    'concrete_code'   => $gradeData['code'],
                    'concrete_ratio'  => $gradeData['ratio'],
                    'cement_ratio'    => $gradeData['cement_ratio'],
                    'sand_ratio'      => $gradeData['sand_ratio'],
                    'aggregate_ratio' => $gradeData['aggregate_ratio'],
                    'status'          => true,
                    'created_by'      => 1,
                ]
            );

            foreach ($gradeData['items'] as $item) {

                ConcreteGradeItem::firstOrCreate(
                    [
                        'plant_id'         => $plantId,
                        'concrete_grade_id'=> $grade->id,
                        'product_id'       => $productIds[$item['name']],
                    ],
                    [
                        'quantity'   => $item['qty'],
                        'status'     => true,
                        'created_by' => 1,
                    ]
                );
            }
        }

        $this->command->info('Concrete grades seeded successfully.');
    }
}