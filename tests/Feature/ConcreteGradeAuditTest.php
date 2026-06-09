<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plant;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\InventoryAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConcreteGradeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected ProductUnit $unit;
    protected ConcreteGrade $concreteGrade;
    protected Product $product1;
    protected Product $product2;

    protected function setUp(): void
    {
        // Skip if running sqlite and migrations fail due to incompatible syntax
        if (isset($_ENV['DB_CONNECTION']) && $_ENV['DB_CONNECTION'] === 'sqlite') {
            $this->markTestSkipped('Database migrations contain SQLite-incompatible MySQL features.');
        }

        parent::setUp();

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->unit = ProductUnit::factory()->create();

        $this->product1 = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->product2 = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // Set session active context
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);

        // Give test user the Platform Admin role
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->user->assignRole($role);

        $this->actingAs($this->user);

        // Create concrete grade with two items
        $this->concreteGrade = ConcreteGrade::create([
            'plant_id' => $this->plant->id,
            'name' => 'Test Grade M20',
            'concrete_ratio' => '1:1.5:3',
            'cement_ratio' => 1,
            'sand_ratio' => 1.5,
            'aggregate_ratio' => 3,
            'status' => true,
            'created_by' => $this->user->id,
        ]);

        ConcreteGradeItem::create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => $this->concreteGrade->id,
            'product_id' => $this->product1->id,
            'quantity' => 100,
            'created_by' => $this->user->id,
        ]);

        ConcreteGradeItem::create([
            'plant_id' => $this->plant->id,
            'concrete_grade_id' => $this->concreteGrade->id,
            'product_id' => $this->product2->id,
            'quantity' => 200,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test removing an item from a concrete grade soft deletes it, sets deleted_by, and records it in InventoryAuditLog.
     */
    public function test_removing_item_soft_deletes_and_records_audit_log(): void
    {
        // Assert items exist initially
        $this->assertCount(2, $this->concreteGrade->items);

        // Request update with only product1, effectively removing product2
        $response = $this->put(route('concretegrades.update', $this->concreteGrade->id), [
            'name' => 'Test Grade M20',
            'concrete_ratio' => '1:1.5:3',
            'cement_ratio' => 1,
            'sand_ratio' => 1.5,
            'aggregate_ratio' => 3,
            'status' => true,
            'items' => [
                [
                    'product_id' => $this->product1->id,
                    'quantity' => 100,
                ]
            ]
        ]);

        $response->assertRedirect();

        // Get the removed item (product2) from db (including trashed)
        $removedItem = ConcreteGradeItem::withTrashed()
            ->where('concrete_grade_id', $this->concreteGrade->id)
            ->where('product_id', $this->product2->id)
            ->first();

        $this->assertNotNull($removedItem);
        $this->assertTrue($removedItem->trashed());
        $this->assertEquals($this->user->id, $removedItem->deleted_by);

        // Assert audit log was recorded
        $log = InventoryAuditLog::where('transaction_type', 'DELETE')
            ->where('reference_type', 'ConcreteGradeItem')
            ->where('reference_id', $removedItem->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertStringContainsString('Deleted: ConcreteGradeItem', $log->remarks);
    }
}
