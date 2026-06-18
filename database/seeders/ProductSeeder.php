<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [

            // ==========================
            // RMC PRODUCTS
            // ==========================
            ['code' => 'M10', 'title' => 'M10 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M15', 'title' => 'M15 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M20', 'title' => 'M20 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M25', 'title' => 'M25 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M30', 'title' => 'M30 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M35', 'title' => 'M35 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M40', 'title' => 'M40 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M45', 'title' => 'M45 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M50', 'title' => 'M50 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M55', 'title' => 'M55 Grade Ready Mix Concrete', 'category_id' => 2],
            ['code' => 'M60', 'title' => 'M60 Grade Ready Mix Concrete', 'category_id' => 2],

            // ==========================
            // CEMENT
            // ==========================
            ['code' => 'OPC43', 'title' => 'OPC 43 Grade Cement', 'category_id' => 1],
            ['code' => 'OPC53', 'title' => 'OPC 53 Grade Cement', 'category_id' => 1],
            ['code' => 'PPC', 'title' => 'Portland Pozzolana Cement', 'category_id' => 1],

            // ==========================
            // SAND
            // ==========================
            ['code' => 'MSAND', 'title' => 'M-Sand', 'category_id' => 3],
            ['code' => 'RSAND', 'title' => 'River Sand', 'category_id' => 3],

            // ==========================
            // AGGREGATES
            // ==========================
            ['code' => 'AGG6', 'title' => '6mm Aggregate', 'category_id' => 4],
            ['code' => 'AGG12', 'title' => '12mm Aggregate', 'category_id' => 4],
            ['code' => 'AGG20', 'title' => '20mm Aggregate', 'category_id' => 4],
            ['code' => 'AGG40', 'title' => '40mm Aggregate', 'category_id' => 4],

            // ==========================
            // ADMIXTURES
            // ==========================
            ['code' => 'ADM001', 'title' => 'Plasticizer Admixture', 'category_id' => 5],
            ['code' => 'ADM002', 'title' => 'Super Plasticizer Admixture', 'category_id' => 5],
            ['code' => 'ADM003', 'title' => 'Retarding Admixture', 'category_id' => 5],
            ['code' => 'ADM004', 'title' => 'Accelerating Admixture', 'category_id' => 5],

            // ==========================
            // SCM
            // ==========================
            ['code' => 'FA001', 'title' => 'Fly Ash', 'category_id' => 6],
            ['code' => 'GGBS001', 'title' => 'Ground Granulated Blast Furnace Slag (GGBS)', 'category_id' => 6],

            // ==========================
            // SERVICES
            // ==========================
            ['code' => 'PUMP', 'title' => 'Concrete Pumping Service', 'category_id' => 7],
            ['code' => 'TRANS', 'title' => 'Transit Mixer Service', 'category_id' => 7],
        ];

        foreach ($items as $item) {
            Product::updateOrCreate(
                ['code' => $item['code']],
                [
                    'plant_id' => 2,
                    'category_id' => $item['category_id'],
                    'unit_id' => 1,
                    'title' => $item['title'],
                    'code' => $item['code'],
                    'status' => 1,
                    'created_by' => 1,
                ]
            );
        }
    }
}
