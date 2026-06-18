<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use App\Models\Plant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation_and_attributes(): void
    {
        $plant = Plant::factory()->create();
        $product = Product::create([
            'plant_id' => $plant->id,
            'entity_id' => $plant->entity_id,
            'title' => 'Test Product',
            'purchase_price' => 100,
            'sales_price' => 120,
            'status' => true,
        ]);

        $this->assertDatabaseHas('mm_products', [
            'id' => $product->id,
            'title' => 'Test Product',
        ]);
    }
}
