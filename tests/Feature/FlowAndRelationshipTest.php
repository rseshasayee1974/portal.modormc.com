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
                    'discount_type' => '%',
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
                    'discount_type' => '%',
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
        $product = Product::factory()->create(['plant_id' => $this->plant->id, 'unit_id' => $this->unit->id]);

        // CREATE
        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $this->customer->id,
            'site_id' => $this->site->id,
            'mix_design_id' => $mixDesign->id,
            'order_date' => '2026-06-18',
            'status' => SalesOrder::STATUS_DRAFT,
        ]);

        $item = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'material_id' => $product->id,
            'required_qty' => 10,
            'uom_id' => $this->unit->id,
        ]);

        $this->assertDatabaseHas('mm_sales_orders', [
            'id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'mix_design_id' => $mixDesign->id,
        ]);

        // READ
        $found = SalesOrder::find($salesOrder->id);
        $this->assertCount(1, $found->items);
        $this->assertEquals($product->id, $found->items->first()->material_id);

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
        // Create a regular user with PRODUCT.delete permission but not system admin
        $regularUser = User::factory()->create();
        $regularRole = \App\Models\Role::firstOrCreate(
            ['name' => 'Operator', 'guard_name' => 'web'],
            ['code' => 'OPERATOR']
        );
        $deletePermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'PRODUCT.delete', 'guard_name' => 'web']);
        $regularRole->givePermissionTo($deletePermission);
        $regularUser->assignRole($regularRole);

        \App\Models\EntityUser::create([
            'user_id' => $regularUser->id,
            'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id,
            'role_id' => $regularRole->id,
        ]);

        $this->actingAs($regularUser);

        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
        ]);

        // Link the product to an active MixDesign that is used in a batch
        $mixDesign = MixDesign::create([
            'plant_id' => $this->plant->id,
            'partner_id' => Patron::factory()->create(['plant_id' => $this->plant->id])->id,
            'concrete_grade_id' => ConcreteGrade::create(['plant_id' => $this->plant->id, 'name' => 'M20', 'status' => 1])->id,
            'design_name' => 'Test Mix 10',
            'design_type' => 'M20',
        ]);

        MixDesignItem::create([
            'plant_id' => $this->plant->id,
            'mix_design_id' => $mixDesign->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'rate' => 10.0,
            'actual_quantity' => 100,
        ]);

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $mixDesign->partner_id,
            'mix_design_id' => $mixDesign->id,
            'total_qty' => 1000,
            'order_no' => 'SO-12345',
            'status' => 1,
        ]);

        \App\Models\Batch::create([
            'plant_id' => $this->plant->id,
            'sales_order_id' => $salesOrder->id,
            'batch_no' => 1,
            'batch_size' => 1.5,
            'status' => 1,
        ]);

        // Assert getIsInUseAttribute evaluates to true
        $this->assertTrue($product->fresh()->is_in_use);

        // Attempt deletion via controller endpoint to simulate user request
        $response = $this->delete(route('products.destroy', $product->id));

        // Assert failure due to validation error redirect with validation errors
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['product']);

        // Verify that the product is still in database and not deleted
        $this->assertDatabaseHas('mm_products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_regular_user_can_update_exempted_fields_on_restricted_product(): void
    {
        $this->withoutExceptionHandling();
        $regularUser = User::factory()->create();
        $regularRole = \App\Models\Role::firstOrCreate(
            ['name' => 'Operator', 'guard_name' => 'web'],
            ['code' => 'OPERATOR']
        );
        $editPermission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'PRODUCT.edit', 'guard_name' => 'web']);
        $regularRole->givePermissionTo($editPermission);
        $regularUser->assignRole($regularRole);

        \App\Models\EntityUser::create([
            'user_id' => $regularUser->id,
            'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id,
            'role_id' => $regularRole->id,
        ]);

        $this->actingAs($regularUser);

        $product = Product::factory()->create([
            'plant_id' => $this->plant->id,
            'unit_id' => $this->unit->id,
            'hsn_code' => 'OLD-HSN',
            'material_code' => 'OLD-MAT',
        ]);

        $mixDesign = MixDesign::create([
            'plant_id' => $this->plant->id,
            'partner_id' => Patron::factory()->create(['plant_id' => $this->plant->id])->id,
            'concrete_grade_id' => ConcreteGrade::create(['plant_id' => $this->plant->id, 'name' => 'M20', 'status' => 1])->id,
            'design_name' => 'Test Mix 10',
            'design_type' => 'M20',
        ]);

        MixDesignItem::create([
            'plant_id' => $this->plant->id,
            'mix_design_id' => $mixDesign->id,
            'product_id' => $product->id,
            'uom_id' => $this->unit->id,
            'rate' => 10.0,
            'actual_quantity' => 100,
        ]);

        $salesOrder = SalesOrder::create([
            'plant_id' => $this->plant->id,
            'customer_id' => $mixDesign->partner_id,
            'mix_design_id' => $mixDesign->id,
            'total_qty' => 1000,
            'order_no' => 'SO-12345',
            'status' => 1,
        ]);

        \App\Models\Batch::create([
            'plant_id' => $this->plant->id,
            'sales_order_id' => $salesOrder->id,
            'batch_no' => 1,
            'batch_size' => 1.5,
            'status' => 1,
        ]);

        $newCategory = \App\Models\ProductCategory::factory()->create(['plant_id' => $this->plant->id]);
        $product = $product->fresh();

        // Attempt updating only exempted fields (category_id, hsn_code, material_code)
        $response = $this->put(route('products.update', $product->id), [
            'title' => $product->title,
            'code' => $product->code,
            'category_id' => $newCategory->id,
            'unit_id' => $product->unit_id,
            'purchase_price' => $product->purchase_price,
            'sales_price' => $product->sales_price,
            'status' => $product->status,
            'hsn_code' => 'NEW-HSN',
            'material_code' => 'NEW-MAT',
            'tax_mode' => $product->tax_mode,
            'purchase_tax_id' => $product->purchase_tax_id,
            'sale_tax_id' => $product->sale_tax_id,
            'is_service' => $product->is_service,
            'product_type' => $product->product_type,
            'stock_alert' => $product->stock_alert,
            'conversion_quantity' => $product->conversion_quantity,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertEquals('New-hsn', $product->fresh()->hsn_code);
        $this->assertEquals('New-mat', $product->fresh()->material_code);
        $this->assertEquals($newCategory->id, $product->fresh()->category_id);
    }
}
