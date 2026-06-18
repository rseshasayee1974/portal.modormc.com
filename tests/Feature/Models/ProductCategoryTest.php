<?php

namespace Tests\Feature\Models;

use App\Models\ProductCategory;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_creation(): void
    {
        $plant = Plant::factory()->create();
        $category = ProductCategory::create([
            'plant_id' => $plant->id,
            'name' => 'Cement Materials',
            'code' => 'CMT',
        ]);

        $this->assertDatabaseHas('mm_product_categories', [
            'id' => $category->id,
            'name' => 'Cement Materials',
        ]);
    }
}
