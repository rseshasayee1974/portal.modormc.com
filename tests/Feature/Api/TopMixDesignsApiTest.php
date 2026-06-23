<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TopMixDesignsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_batches');
        Schema::dropIfExists('mm_sales_orders');
        Schema::dropIfExists('mm_concrete_grades');
        Schema::dropIfExists('mm_mix_designs');

        Schema::create('mm_concrete_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_mix_designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->string('design_name')->nullable();
            $table->string('design_code')->nullable();
            $table->string('design_type')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mix_design_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->decimal('batch_size', 10, 3)->default(0);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_top_mix_designs_api_returns_top_five_from_batches_using_concrete_grade_name(): void
    {
        DB::table('mm_concrete_grades')->insert([
            ['id' => 101, 'plant_id' => 2, 'name' => 'M40', 'deleted_at' => null],
            ['id' => 102, 'plant_id' => 2, 'name' => 'M30', 'deleted_at' => null],
            ['id' => 103, 'plant_id' => 2, 'name' => 'M25', 'deleted_at' => null],
            ['id' => 104, 'plant_id' => 2, 'name' => 'M20', 'deleted_at' => null],
            ['id' => 105, 'plant_id' => 2, 'name' => 'M15', 'deleted_at' => null],
        ]);

        DB::table('mm_mix_designs')->insert([
            ['id' => 1, 'plant_id' => 2, 'design_name' => 'M40 Premium', 'design_code' => 'M40P', 'design_type' => 'M40', 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 2, 'design_name' => 'M30 Standard', 'design_code' => 'M30S', 'design_type' => 'M30', 'deleted_at' => null],
            ['id' => 3, 'plant_id' => 2, 'design_name' => 'M25 Value', 'design_code' => 'M25V', 'design_type' => 'M25', 'deleted_at' => null],
            ['id' => 4, 'plant_id' => 2, 'design_name' => 'M20 Classic', 'design_code' => 'M20C', 'design_type' => 'M20', 'deleted_at' => null],
            ['id' => 5, 'plant_id' => 2, 'design_name' => 'M15 Lite', 'design_code' => 'M15L', 'design_type' => 'M15', 'deleted_at' => null],
            ['id' => 6, 'plant_id' => 2, 'design_name' => 'M10 Base', 'design_code' => 'M10B', 'design_type' => 'M10', 'deleted_at' => null],
        ]);

        DB::table('mm_sales_orders')->insert([
            ['id' => 11, 'mix_design_id' => 1, 'deleted_at' => null],
            ['id' => 12, 'mix_design_id' => 2, 'deleted_at' => null],
            ['id' => 13, 'mix_design_id' => 3, 'deleted_at' => null],
            ['id' => 14, 'mix_design_id' => 4, 'deleted_at' => null],
            ['id' => 15, 'mix_design_id' => 5, 'deleted_at' => null],
            ['id' => 16, 'mix_design_id' => 6, 'deleted_at' => null],
        ]);

        DB::table('mm_batches')->insert([
            ['plant_id' => 2, 'sales_order_id' => 11, 'batch_size' => 5.000, 'start_time' => '2026-05-10 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 11, 'batch_size' => 2.500, 'start_time' => '2026-05-11 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 12, 'batch_size' => 6.000, 'start_time' => '2026-05-12 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 13, 'batch_size' => 4.500, 'start_time' => '2026-05-13 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 14, 'batch_size' => 3.000, 'start_time' => '2026-05-14 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 15, 'batch_size' => 2.000, 'start_time' => '2026-05-15 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 2, 'sales_order_id' => 16, 'batch_size' => 1.000, 'start_time' => '2026-05-16 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plant_id' => 3, 'sales_order_id' => 16, 'batch_size' => 99.000, 'start_time' => '2026-05-16 10:00:00', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/dashboard/top-mix-designs?from_date=2026-05-01&to_date=2026-05-18&plant_id=2&type=daily');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    [
                        'mix_design_id' => 1,
                        'design_name' => 'M40 Premium',
                        'design_code' => 'M40P',
                        'grade' => 'M40',
                        'total_batch_size' => ['qty' => 7.5, 'unit' => 'cbm'],
                        'total_batch_count' => ['qty' => 2, 'unit' => 'count'],
                    ],
                    [
                        'mix_design_id' => 2,
                        'design_name' => 'M30 Standard',
                        'design_code' => 'M30S',
                        'grade' => 'M30',
                        'total_batch_size' => ['qty' => 6.0, 'unit' => 'cbm'],
                        'total_batch_count' => ['qty' => 1, 'unit' => 'count'],
                    ],
                    [
                        'mix_design_id' => 3,
                        'design_name' => 'M25 Value',
                        'design_code' => 'M25V',
                        'grade' => 'M25',
                    ],
                    [
                        'mix_design_id' => 4,
                        'design_name' => 'M20 Classic',
                        'design_code' => 'M20C',
                        'grade' => 'M20',
                    ],
                    [
                        'mix_design_id' => 5,
                        'design_name' => 'M15 Lite',
                        'design_code' => 'M15L',
                        'grade' => 'M15',
                    ],
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }
}
