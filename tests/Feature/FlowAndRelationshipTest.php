<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Quantity;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\Accounts;
use App\Models\AccountsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use InvalidArgumentException;

class FlowAndRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Plant $plant;
    protected Patron $vendor;
    protected Patron $customer;
    protected Site $site;
    protected ProductUnit $unit;
    protected Currency $currency;
    protected Ledger $purchaseLedger;
    protected Ledger $sundryLedger;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);

        $this->user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $this->vendor = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Raw Material Supplier']);
        $this->customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Concrete Customer']);
        $this->site = Site::factory()->create(['plant_id' => $this->plant->id, 'name' => 'Construction Site A']);
        $this->unit = ProductUnit::factory()->create(['unit_code' => 'KG', 'unit_name' => 'Kilogram']);
        $this->currency = Currency::factory()->create();

        // Seed accounting ledgers to prevent PO bill generation exceptions
        $entityId = $this->plant->entity_id;
        $account = Accounts::factory()->create(['plant_id' => $this->plant->id]);
        $accountsType = AccountsType::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_id' => $account->id,
        ]);

        $this->sundryLedger = Ledger::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_type_id' => $accountsType->id,
            'title' => 'Sundry Creditors Ledger',
        ]);
        $this->purchaseLedger = Ledger::factory()->create([
            'plant_id' => $this->plant->id,
            'entity_id' => $entityId,
            'account_type_id' => $accountsType->id,
            'title' => 'Purchase Ledger',
        ]);

        // Fallback ledgers
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'GST Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Shipping Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Adjustment Account']);
        Ledger::factory()->create(['plant_id' => $this->plant->id, 'entity_id' => $entityId, 'account_type_id' => $accountsType->id, 'title' => 'Round Off Account']);

        // Set session context
        session([
            'active_plant_id' => $this->plant->id,
            'active_entity_id' => $this->plant->entity_id,
        ]);

        // Assign super-admin bypass permissions
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'],
            ['code' => 'PLATFORM_ADMIN']
        );
        $this->user->assignRole($role);

        $this->actingAs($this->user);
    }

    // ──────────────────────────────────────────────────────────────
    // 1. PRODUCT CRUD & POLICY TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_product_crud_lifecycle(): void
    {
        // CREATE
        $product = Product::create([
            'plant_id' => $this->plant->id,
            'entity_id' => $this->plant->entity_id,
            'title' => 'Cement Grade 53',
            'code' => 'CEMENT-53',
            'purchase_price' => 350.00,
            'sales_price' => 450.00,
            'status' => true,
            'unit_id' => $this->unit->id,
        ]);

        $this->assertDatabaseHas('mm_products', [
            'id' => $product->id,
            'title' => 'Cement Grade 53',
        ]);

        // READ
        $found = Product::find($product->id);
        $this->assertEquals('Cement Grade 53', $found->title);
        $this->assertFalse($found->is_in_use); // Not used yet

        // UPDATE
        $found->update(['purchase_price' => 375.00]);
        $this->assertEquals(375.00, $found->fresh()->purchase_price);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($product);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. CONCRETE GRADE CRUD TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_concrete_grade_crud_lifecycle(): void
    {
        // CREATE
        $grade = ConcreteGrade::create([
            'plant_id' => $this->plant->id,
            'name' => 'M30 Concrete',
            'concrete_code' => 'M30',
            'status' => true,
        ]);

        $this->assertDatabaseHas('mm_concrete_grades', [
            'id' => $grade->id,
            'name' => 'M30 Concrete',
        ]);

        // READ & RELATIONSHIPS
        $found = ConcreteGrade::find($grade->id);
        $this->assertEquals('M30 Concrete', $found->name);
        $this->assertCount(0, $found->items);

        // UPDATE
        $found->update(['name' => 'M30 High Strength']);
        $this->assertEquals('M30 High Strength', $found->fresh()->name);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($grade);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. MIX DESIGN CRUD TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_mix_design_crud_lifecycle(): void
    {
        $grade = ConcreteGrade::create([
            'plant_id' => $this->plant->id,
            'name' => 'M25 Concrete',
            'concrete_code' => 'M25',
            'status' => true,
        ]);

        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // CREATE
        $mixDesign = MixDesign::factory()->create([
            'plant_id' => $this->plant->id,
            'partner_id' => $this->customer->id,
            'concrete_grade_id' => $grade->id,
            'unit_id' => $this->unit->id,
            'design_name' => 'Standard M25 Mix',
            'design_code' => 'M25-STD',
        ]);

        $item = MixDesignItem::create([
            'plant_id' => $this->plant->id,
            'mix_design_id' => $mixDesign->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'rate' => 12.0,
            'actual_quantity' => 100,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('mm_mix_designs', [
            'id' => $mixDesign->id,
            'design_name' => 'Standard M25 Mix',
        ]);

        // READ
        $found = MixDesign::find($mixDesign->id);
        $this->assertEquals('Standard M25 Mix', $found->design_name);
        $this->assertCount(1, $found->items);
        $this->assertEquals($product->id, $found->items->first()->product_id);

        // UPDATE
        $found->update(['design_name' => 'Premium M25 Mix']);
        $this->assertEquals('Premium M25 Mix', $found->fresh()->design_name);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($mixDesign);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. PURCHASE ORDER CRUD TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_purchase_order_crud_lifecycle(): void
    {
        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // CREATE via storeWithItems helper
        $po = PurchaseOrder::storeWithItems([
            'plant_id' => $this->plant->id,
            'vendor_id' => $this->vendor->id,
            'date_order' => '2026-06-18',
            'discount_amount' => 50.00,
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_uom' => $this->unit->id,
                    'product_quantity' => 100,
                    'unit_price' => 10,
                    'discount_type' => 'percentage',
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $this->assertDatabaseHas('mm_purchase_orders', [
            'id' => $po->id,
            'vendor_id' => $this->vendor->id,
        ]);

        // READ
        $found = PurchaseOrder::find($po->id);
        $this->assertCount(1, $found->items);
        $this->assertEquals($product->id, $found->items->first()->product_id);

        // UPDATE
        $found->updateWithItems([
            'discount_amount' => 75.00,
            'items' => [
                [
                    'id' => $found->items->first()->id,
                    'product_id' => $product->id,
                    'product_uom' => $this->unit->id,
                    'product_quantity' => 150, // Updated qty
                    'unit_price' => 10,
                    'discount_type' => 'percentage',
                    'discount_amount' => 0,
                ]
            ]
        ]);

        $found->refresh();
        $this->assertEquals(75.00, $found->discount_amount);
        $this->assertEquals(150, $found->items->first()->product_quantity);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($po);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. SALES ORDER CRUD TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_sales_order_crud_lifecycle(): void
    {
        $mixDesign = MixDesign::factory()->create(['plant_id' => $this->plant->id]);

        // CREATE
        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'patron_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'order_date' => '2026-06-18',
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        $item = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'mix_design_id' => $mixDesign->id,
            'quantity' => 10,
            'rate' => 500,
            'amount_total' => 5000,
        ]);

        $this->assertDatabaseHas('mm_sales_orders', [
            'id' => $salesOrder->id,
            'patron_id' => $this->customer->id,
        ]);

        // READ
        $found = SalesOrder::find($salesOrder->id);
        $this->assertCount(1, $found->items);
        $this->assertEquals($mixDesign->id, $found->items->first()->mix_design_id);

        // UPDATE
        $found->update(['status' => SalesOrder::STATUS_CONFIRMED]);
        $this->assertEquals(SalesOrder::STATUS_CONFIRMED, $found->fresh()->status);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($salesOrder);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. QUANTITY CRUD & INVENTORY INTEGRITY TESTS
    // ──────────────────────────────────────────────────────────────

    public function test_quantity_crud_lifecycle(): void
    {
        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // CREATE
        $quantity = Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 200,
            'opening_quantity' => 100,
            'date' => '2026-06-18',
            'is_warehouse' => true,
            'status' => 1,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('mm_quantity', [
            'id' => $quantity->id,
            'quantity' => 200,
        ]);

        // READ
        $found = Quantity::find($quantity->id);
        $this->assertEquals(200, $found->quantity);

        // UPDATE
        $found->update(['quantity' => 250]);
        $this->assertEquals(250, $found->fresh()->quantity);

        // DELETE
        $found->delete();
        $this->assertSoftDeleted($quantity);
    }

    public function test_quantity_cannot_be_negative(): void
    {
        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'quantity' => -10, // Negative value triggers Validation Exception in hook
            'date' => '2026-06-18',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. IN USE BUSINESS RULE PREVENTS PRODUCT DELETION
    // ──────────────────────────────────────────────────────────────

    public function test_policy_prevents_deletion_of_product_in_use(): void
    {
        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // Link the product to a Quantity record (representing inventory)
        $quantity = Quantity::create([
            'plant_id' => $this->plant->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'quantity' => 50,
            'date' => '2026-06-18',
            'is_warehouse' => true,
            'status' => 1,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        // Assert getIsInUseAttribute evaluates to true
        $this->assertTrue($product->fresh()->is_in_use);

        // Attempt deletion via controller endpoint to simulate user request
        $response = $this->delete(route('products.destroy', $product->id));

        // Assert failure due to validation error redirect with 'error' message
        $response->assertStatus(302);
        $response->assertSessionHas('error');

        // Verify that the product is still in database and not deleted
        $this->assertDatabaseHas('mm_products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }
}
