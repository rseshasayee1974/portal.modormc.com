<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\ProductCategory::factory()->count(5)->create()->each(function ($category) {
            \App\Models\ProductCategory::factory()->count(2)->create([
                'parent_id' => $category->id,
                'entity_id' => $category->entity_id,
            ]);
        });
    }
}
