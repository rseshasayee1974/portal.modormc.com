<?php
/*
Author: Cipher
Created: 2026-11-17 11:39:15 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardRepositoryTest extends TestCase
{
    private DashboardRepository $repository;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'dashboard_repo_test',
            'database.connections.dashboard_repo_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $this->createSchema();
        $this->seedTestData();

        $this->adminUser = new User();
        $this->adminUser->forceFill(['id' => 1, 'default_plant_id' => 1]);
        $this->adminUser->setRelation('roles', new Collection([
            new Role(['code' => 'SUPER_ADMIN', 'name' => 'Super Admin', 'guard_name' => 'web']),
        ]));
        $this->actingAs($this->adminUser);

        $this->repository = new DashboardRepository();
    }

    private function createSchema(): void
    {
        Schema::create('mm_entities', function (Blueprint $t) {
            $t->id();
            $t->boolean('is_suspended')->default(false);
            $t->softDeletes();
        });

        Schema::create('mm_plants', function (Blueprint $t) {
            $t->id();
            $t->integer('entity_id');
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->softDeletes();
        });

        Schema::create('mm_entity_users', function (Blueprint $t) {
            $t->id();
            $t->integer('user_id');
            $t->integer('entity_id');
            $t->integer('plant_id')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_patrons', function (Blueprint $t) {
            $t->id();
            $t->string('legal_name');
            $t->string('operational_status')->default('active');
            $t->softDeletes();
        });

        Schema::create('mm_sites', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->softDeletes();
        });

        Schema::create('mm_concrete_grades', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->string('name');
            $t->softDeletes();
        });

        Schema::create('mm_product_units', function (Blueprint $t) {
            $t->id();
            $t->string('unit_name');
            $t->string('unit_code')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_mix_designs', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->integer('concrete_grade_id')->nullable();
            $t->string('design_name');
            $t->string('design_code')->nullable();
            $t->string('design_type')->nullable();
            $t->integer('unit_id')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_sales_orders', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('site_id')->nullable();
            $t->integer('mix_design_id')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_machines', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->string('registration')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_personnels', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_batches', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->integer('sales_order_id')->nullable();
            $t->decimal('batch_size', 10, 3)->default(1);
            $t->timestamp('start_time')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_batch_materials', function (Blueprint $t) {
            $t->id();
            $t->integer('batch_id');
            $t->string('material_name');
            $t->decimal('target_qty', 10, 3)->default(0);
            $t->decimal('actual_qty', 10, 3)->default(0);
            $t->decimal('deviation_quantity', 10, 3)->default(0);
            $t->integer('uom_id')->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_dispatches', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->integer('batch_id')->nullable();
            $t->integer('sales_order_id')->nullable();
            $t->integer('customer_id')->nullable();
            $t->integer('unload_site_id')->nullable();
            $t->integer('mixdesign_id')->nullable();
            $t->integer('truck_id')->nullable();
            $t->integer('driver_id')->nullable();
            $t->integer('uom_id')->nullable();
            $t->string('dispatch_no')->nullable();
            $t->dateTime('dispatch_time')->nullable();
            $t->string('dispatch_status')->nullable();
            $t->string('payment_mode')->nullable();
            $t->decimal('load_rate', 10, 2)->default(0);
            $t->decimal('load_untax_amount', 12, 2)->default(0);
            $t->decimal('load_tax_amount', 12, 2)->default(0);
            $t->decimal('load_total_amount', 12, 2)->default(0);
            $t->decimal('net_weight', 10, 3)->default(0);
            $t->decimal('delivered_qty', 10, 3)->nullable();
            $t->softDeletes();
        });

        Schema::create('mm_products', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->string('title');
            $t->string('code')->nullable();
            $t->decimal('stock_alert', 10, 2)->default(0);
            $t->integer('unit_id')->nullable();
            $t->boolean('is_service')->default(false);
            $t->softDeletes();
        });

        Schema::create('mm_quantity', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id')->nullable();
            $t->integer('product_id')->nullable();
            $t->decimal('quantity', 10, 2)->default(0);
            $t->softDeletes();
        });

        Schema::create('mm_dashboard_alerts', function (Blueprint $t) {
            $t->id();
            $t->integer('plant_id');
            $t->string('message');
            $t->timestamps();
            $t->softDeletes();
        });
    }

    private function seedTestData(): void
    {
        DB::table('mm_entities')->insert(['id' => 1, 'is_suspended' => false]);
        DB::table('mm_plants')->insert([
            ['id' => 1, 'entity_id' => 1, 'name' => 'Main Plant', 'is_active' => true],
            ['id' => 2, 'entity_id' => 1, 'name' => 'Secondary Plant', 'is_active' => true],
        ]);
        DB::table('mm_entity_users')->insert([
            ['user_id' => 1, 'entity_id' => 1, 'plant_id' => null, 'deleted_at' => null],
        ]);

        DB::table('mm_patrons')->insert([
            ['id' => 1, 'legal_name' => 'Alpha Builders'],
            ['id' => 2, 'legal_name' => 'Beta Developers'],
        ]);

        DB::table('mm_sites')->insert([
            ['id' => 1, 'name' => 'Metro Site'],
        ]);

        DB::table('mm_concrete_grades')->insert([
            ['id' => 1, 'plant_id' => 1, 'name' => 'M25'],
            ['id' => 2, 'plant_id' => 1, 'name' => 'M30'],
        ]);

        DB::table('mm_product_units')->insert([
            ['id' => 1, 'unit_name' => 'Cubic Meter', 'unit_code' => 'm³'],
            ['id' => 2, 'unit_name' => 'Metric Ton', 'unit_code' => 'MT'],
        ]);

        DB::table('mm_mix_designs')->insert([
            ['id' => 1, 'plant_id' => 1, 'concrete_grade_id' => 1, 'design_name' => 'M25 Standard', 'design_code' => 'MD-M25', 'design_type' => 'M25', 'unit_id' => 1],
            ['id' => 2, 'plant_id' => 1, 'concrete_grade_id' => 2, 'design_name' => 'M30 High Grade', 'design_code' => 'MD-M30', 'design_type' => 'M30', 'unit_id' => 1],
        ]);

        DB::table('mm_sales_orders')->insert([
            ['id' => 1, 'plant_id' => 1, 'customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1],
            ['id' => 2, 'plant_id' => 1, 'customer_id' => 2, 'site_id' => 1, 'mix_design_id' => 2],
        ]);

        DB::table('mm_machines')->insert([
            ['id' => 1, 'plant_id' => 1, 'registration' => 'TM-01'],
            ['id' => 2, 'plant_id' => 1, 'registration' => 'TM-02'],
        ]);

        DB::table('mm_personnels')->insert([
            ['id' => 1, 'plant_id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
        ]);

        DB::table('mm_batches')->insert([
            ['id' => 1, 'plant_id' => 1, 'sales_order_id' => 1, 'batch_size' => 6.0, 'start_time' => '2026-11-17 08:00:00'],
            ['id' => 2, 'plant_id' => 1, 'sales_order_id' => 2, 'batch_size' => 4.0, 'start_time' => '2026-11-17 09:00:00'],
        ]);

        DB::table('mm_batch_materials')->insert([
            ['id' => 1, 'batch_id' => 1, 'material_name' => 'Cement', 'target_qty' => 1800, 'actual_qty' => 1805, 'deviation_quantity' => 5, 'uom_id' => 2],
            ['id' => 2, 'batch_id' => 1, 'material_name' => 'Sand', 'target_qty' => 4200, 'actual_qty' => 4190, 'deviation_quantity' => -10, 'uom_id' => 2],
            ['id' => 3, 'batch_id' => 1, 'material_name' => 'Flyash', 'target_qty' => 0, 'actual_qty' => 0, 'deviation_quantity' => 0, 'uom_id' => 2],
        ]);

        // Dispatches:
        // 1: Cash dispatch, batch 1, delivered_qty null (should fallback to batch 1 size 6.0)
        // 2: Credit dispatch, batch 2, delivered_qty 4.0
        // 3: Cancelled dispatch (should be excluded from summaries)
        DB::table('mm_dispatches')->insert([
            [
                'id' => 1,
                'plant_id' => 1,
                'batch_id' => 1,
                'sales_order_id' => 1,
                'customer_id' => 1,
                'unload_site_id' => 1,
                'mixdesign_id' => 1,
                'truck_id' => 1,
                'driver_id' => 1,
                'uom_id' => 1,
                'dispatch_no' => 'DP-1001',
                'dispatch_time' => '2026-11-17 08:30:00',
                'dispatch_status' => 'Delivered',
                'payment_mode' => 'cash',
                'load_rate' => 4500.00,
                'load_untax_amount' => 27000.00,
                'load_tax_amount' => 4860.00,
                'load_total_amount' => 31860.00,
                'net_weight' => 14.4,
                'delivered_qty' => null, // fallback to batch 1 (6.0)
            ],
            [
                'id' => 2,
                'plant_id' => 1,
                'batch_id' => 2,
                'sales_order_id' => 2,
                'customer_id' => 2,
                'unload_site_id' => 1,
                'mixdesign_id' => 2,
                'truck_id' => 2,
                'driver_id' => 1,
                'uom_id' => 1,
                'dispatch_no' => 'DP-1002',
                'dispatch_time' => '2026-11-17 09:30:00',
                'dispatch_status' => 'Delivered',
                'payment_mode' => 'credit',
                'load_rate' => 5000.00,
                'load_untax_amount' => 20000.00,
                'load_tax_amount' => 3600.00,
                'load_total_amount' => 23600.00,
                'net_weight' => 9.6,
                'delivered_qty' => 4.0,
            ],
            [
                'id' => 3,
                'plant_id' => 1,
                'batch_id' => 1,
                'sales_order_id' => 1,
                'customer_id' => 1,
                'unload_site_id' => 1,
                'mixdesign_id' => 1,
                'truck_id' => 1,
                'driver_id' => 1,
                'uom_id' => 1,
                'dispatch_no' => 'DP-1003',
                'dispatch_time' => '2026-11-17 10:00:00',
                'dispatch_status' => 'Cancelled',
                'payment_mode' => 'cash',
                'load_rate' => 4500.00,
                'load_untax_amount' => 27000.00,
                'load_tax_amount' => 4860.00,
                'load_total_amount' => 31860.00,
                'net_weight' => 14.4,
                'delivered_qty' => 6.0,
            ],
        ]);

        // Products and Quantity
        DB::table('mm_products')->insert([
            ['id' => 1, 'plant_id' => 1, 'title' => 'OPC 53 Cement', 'code' => 'CEM-53', 'stock_alert' => 50.00, 'unit_id' => 2, 'is_service' => false],
            ['id' => 2, 'plant_id' => 1, 'title' => 'River Sand', 'code' => 'SND-01', 'stock_alert' => 100.00, 'unit_id' => 2, 'is_service' => false],
            ['id' => 3, 'plant_id' => 1, 'title' => 'Pumping Charge', 'code' => 'SRV-01', 'stock_alert' => 0.00, 'unit_id' => null, 'is_service' => true],
        ]);

        DB::table('mm_quantity')->insert([
            ['id' => 1, 'plant_id' => 1, 'product_id' => 1, 'quantity' => 120.00],
            ['id' => 2, 'plant_id' => 1, 'product_id' => 2, 'quantity' => 80.00],
        ]);

        DB::table('mm_dashboard_alerts')->insert([
            ['id' => 1, 'plant_id' => 1, 'message' => 'Sand stock below alert level', 'created_at' => now()],
        ]);
    }

    public function test_sales_summary_handles_both_percentage_keys_and_excludes_cancelled(): void
    {
        $summary = $this->repository->getSalesSummary(['plant_id' => 1]);

        $this->assertEquals(55460.00, $summary['total_sales']);
        $this->assertEquals(31860.00, $summary['cash_sales']['amount']);
        $this->assertEquals(23600.00, $summary['credit_sales']['amount']);
        $this->assertSame(57.4, $summary['cash_sales']['percentage']);
        $this->assertSame(57.4, $summary['cash_sales']['%']);
        $this->assertSame(42.6, $summary['credit_sales']['percentage']);
        $this->assertSame(42.6, $summary['credit_sales']['%']);
    }

    public function test_sales_stats_delivers_volume_and_trips(): void
    {
        $stats = $this->repository->getSalesStats(['plant_id' => 1]);

        $this->assertEquals(24.0, $stats['mt']); // 14.4 + 9.6
        $this->assertEquals(10.0, $stats['unit_cft']); // 6.0 (batch fallback) + 4.0
        $this->assertSame(2, $stats['trips']); // cancelled excluded
    }

    public function test_dispatch_sales_amounts_breakdown(): void
    {
        $amounts = $this->repository->getDispatchSalesAmounts(['plant_id' => 1]);

        $this->assertEquals(55460.00, $amounts['total_dispatch_sales_amount']);
        $this->assertEquals(31860.00, $amounts['cash_sales_amount']);
        $this->assertEquals(23600.00, $amounts['credit_sales_amount']);
        $this->assertSame(1, $amounts['cash_dispatch_count']);
        $this->assertSame(1, $amounts['credit_dispatch_count']);
        $this->assertEquals(14.4, $amounts['cash_quantity_mt']);
        $this->assertEquals(9.6, $amounts['credit_quantity_mt']);
        $this->assertEquals(6.0, $amounts['cash_quantity_cft']); // batch fallback
        $this->assertEquals(4.0, $amounts['credit_quantity_cft']);
    }

    public function test_sales_details_by_payment_mode_structure(): void
    {
        $details = $this->repository->getSalesDetailsByPaymentMode(['plant_id' => 1]);

        $this->assertEquals(31860.00, $details['cash_sales']['sales_amount']['amount']);
        $this->assertEquals(6.0, $details['cash_sales']['quantity']['cbm']['qty']);
        $this->assertEquals(round(6.0 * 35.3147, 3), $details['cash_sales']['quantity']['cft']['qty']);
        $this->assertEquals(14.4, $details['cash_sales']['quantity']['mtr']['qty']);
        $this->assertSame(1, $details['cash_sales']['trips']['dispatch_count']);

        $this->assertEquals(23600.00, $details['credit_sales']['sales_amount']['amount']);
        $this->assertEquals(4.0, $details['credit_sales']['quantity']['cbm']['qty']);
        $this->assertSame(1, $details['credit_sales']['trips']['dispatch_count']);
    }

    public function test_dispatch_batching_summary(): void
    {
        $summary = $this->repository->getDispatchBatchingSummary(['plant_id' => 1]);

        $this->assertEquals(10.0, $summary['total_dispatch_batch_size']['qty']); // 6.0 + 4.0
        $this->assertEquals(24.0, $summary['total_dispatch_net_quantity']['mtr']['qty']); // 14.4 + 9.6
        $this->assertEquals(round(10.0 * 35.3147, 3), $summary['total_dispatch_net_quantity']['cft']['qty']);
        $this->assertSame(2, $summary['total_batching_count']['qty']);
    }

    public function test_dispatch_details_by_truck(): void
    {
        $rows = $this->repository->getDispatchDetailsByTruck(['plant_id' => 1]);

        $this->assertCount(2, $rows);
        $this->assertSame('TM-01', $rows[0]['truck_registration']);
        $this->assertSame(1, $rows[0]['total_dispatch_count']['total_count']);
        $this->assertEquals(6.0, $rows[0]['total_batch_size']['qty']);
        $this->assertEquals(round(6.0 * 35.3147, 3), $rows[0]['total_qty']['cft']['qty']);
        $this->assertEquals(14.4, $rows[0]['total_qty']['mtr']['qty']);
    }

    public function test_dispatch_details_list_with_relations(): void
    {
        $list = $this->repository->getDispatchDetails(['plant_id' => 1]);

        $this->assertCount(3, $list); // list returns all dispatches including cancelled for tracking
        $first = $list->firstWhere('dispatch_no', 'DP-1001');

        $this->assertNotNull($first);
        $this->assertSame('Alpha Builders', $first['customer']['name']);
        $this->assertSame('Metro Site', $first['unload_site']['name']);
        $this->assertSame('M25 Standard', $first['mix_design']['name']);
        $this->assertSame('m³', $first['delivered_qty']['unit']);
        $this->assertEquals(6.0, $first['delivered_qty']['qty']); // batch fallback
        $this->assertSame('TM-01', $first['truck']['registration']);
        $this->assertSame('John Doe', $first['driver']['name']);
        $this->assertEquals(31860.00, $first['financials']['load_total_amount']);
    }

    public function test_top_products_with_dates_and_tonnage(): void
    {
        $products = $this->repository->getTopProducts([
            'plant_id' => 1,
            'from_date' => '2026-11-17',
            'to_date' => '2026-11-17',
        ]);

        $this->assertCount(2, $products);
        $this->assertSame('M25 Standard', $products[0]->product_name);
        $this->assertEquals(14.4, $products[0]->tonnage);
        $this->assertSame(1, $products[0]->trips);

        $this->assertSame('M30 High Grade', $products[1]->product_name);
        $this->assertEquals(9.6, $products[1]->tonnage);
    }

    public function test_top_mix_designs_from_batches(): void
    {
        $designs = $this->repository->getTopMixDesignsFromBatches(['plant_id' => 1]);

        $this->assertCount(2, $designs);
        $this->assertSame('M25 Standard', $designs[0]['design_name']);
        $this->assertSame('M25', $designs[0]['grade']);
        $this->assertEquals(6.0, $designs[0]['total_batch_size']['qty']);
        $this->assertSame(1, $designs[0]['total_batch_count']['qty']);
    }

    public function test_top_mix_designs_falls_back_to_dispatches_when_no_batches_exist(): void
    {
        DB::table('mm_dispatches')->insert([
            'id' => 99,
            'plant_id' => 2,
            'sales_order_id' => null,
            'customer_id' => 1,
            'unload_site_id' => 1,
            'mixdesign_id' => 1,
            'truck_id' => 1,
            'driver_id' => 1,
            'uom_id' => 1,
            'dispatch_no' => 'DP-9099',
            'dispatch_time' => '2026-11-17 10:00:00',
            'dispatch_status' => 'Delivered',
            'payment_mode' => 'cash',
            'load_rate' => 4500.00,
            'load_untax_amount' => 38250.00,
            'load_tax_amount' => 6885.00,
            'load_total_amount' => 45135.00,
            'net_weight' => 20.4,
            'delivered_qty' => 8.5,
        ]);

        $designs = $this->repository->getTopMixDesignsFromBatches(['plant_id' => 2]);

        $this->assertCount(1, $designs);
        $this->assertSame('M25 Standard', $designs[0]['design_name']);
        $this->assertSame('M25', $designs[0]['grade']);
        $this->assertEquals(8.5, $designs[0]['total_batch_size']['qty']);
        $this->assertSame(1, $designs[0]['total_batch_count']['qty']);
    }

    public function test_stock_details_excludes_services_and_evaluates_alerts(): void
    {
        $stock = $this->repository->getStockDetails(['plant_id' => 1]);

        $this->assertCount(2, $stock); // Service excluded
        $cement = $stock->firstWhere('product_name', 'OPC 53 Cement');
        $sand = $stock->firstWhere('product_name', 'River Sand');

        $this->assertNotNull($cement);
        $this->assertEquals(120.00, $cement['current_stock']['qty']);
        $this->assertSame('MT', $cement['current_stock']['unit']);
        $this->assertTrue($cement['stock_status']['in_stock']);
        $this->assertFalse($cement['stock_status']['below_alert']);

        $this->assertNotNull($sand);
        $this->assertEquals(80.00, $sand['current_stock']['qty']);
        $this->assertTrue($sand['stock_status']['in_stock']);
        $this->assertTrue($sand['stock_status']['below_alert']); // 80 <= 100
    }

    public function test_trips_details(): void
    {
        $trips = $this->repository->getTripsDetails(['plant_id' => 1]);

        $this->assertCount(2, $trips);
        $tm01 = $trips->firstWhere('vehicle_no', 'TM-01');
        $this->assertNotNull($tm01);
        $this->assertSame(1, $tm01['count']);
        $this->assertEquals(6.0, $tm01['m3']);
    }

    public function test_customer_details_with_material_consumption(): void
    {
        $customers = $this->repository->getCustomerDetails(['plant_id' => 1]);

        $this->assertCount(2, $customers);
        $alpha = $customers->firstWhere('customer_name', 'Alpha Builders');

        $this->assertNotNull($alpha);
        $this->assertSame('M25', $alpha['grade']);
        $this->assertSame(1, $alpha['total_dispatch_count']['qty']);
        $this->assertCount(2, $alpha['material_consumption']);
        $this->assertNull(collect($alpha['material_consumption'])->firstWhere('material_name', 'Flyash'));

        $cement = collect($alpha['material_consumption'])->firstWhere('material_name', 'Cement');
        $this->assertNotNull($cement);
        $this->assertEquals(1800.0, $cement['target_qty']['qty']);
        $this->assertEquals(1805.0, $cement['actual_qty']['qty']);
        $this->assertEquals(5.0, $cement['deviation_qty']['qty']);
        $this->assertSame('MT', $cement['target_qty']['unit']);
    }

    public function test_alerts_and_plants(): void
    {
        $alerts = $this->repository->getAlerts(['plant_id' => 1]);
        $this->assertCount(1, $alerts);
        $this->assertSame('Sand stock below alert level', $alerts[0]->message);

        $plants = $this->repository->getPlants();
        $this->assertCount(2, $plants);
        $this->assertSame(['Main Plant', 'Secondary Plant'], $plants->pluck('plant_name')->all());
    }
}
