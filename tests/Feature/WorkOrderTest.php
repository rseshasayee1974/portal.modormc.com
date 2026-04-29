<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Seed some dependencies
        ProductUnit::factory()->create(['unit_name' => 'kg', 'unit_code' => 'KG']);
        Product::factory()->create(['name' => 'Cement', 'code' => 'CEM01']);
    }

    public function test_can_list_work_orders()
    {
        WorkOrder::factory(5)->create();

        $response = $this->get(route('work-orders.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WorkOrders/Index')
            ->has('workOrders', 5)
        );
    }

    public function test_can_create_work_order()
    {
        $product = Product::first();
        $uom = ProductUnit::first();

        $data = [
            'product_id' => $product->id,
            'quantity' => 100,
            'uom_id' => $uom->id,
            'items' => [
                ['material_id' => $product->id, 'required_qty' => 50, 'uom_id' => $uom->id]
            ],
            'operations' => [
                ['operation_name' => 'Mixing', 'sequence' => 1, 'duration' => 60]
            ]
        ];

        $response = $this->post(route('work-orders.store'), $data);

        $response->assertRedirect();
        $this->assertDatabaseHas('work_orders', ['quantity' => 100]);
        $this->assertDatabaseHas('work_order_items', ['required_qty' => 50]);
        $this->assertDatabaseHas('work_order_operations', ['operation_name' => 'Mixing']);
    }
}
