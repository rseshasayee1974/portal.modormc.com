<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerDetailsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_dispatches');
        Schema::dropIfExists('mm_batch_materials');
        Schema::dropIfExists('mm_batches');
        Schema::dropIfExists('mm_product_units');
        Schema::dropIfExists('mm_concrete_grades');
        Schema::dropIfExists('mm_mix_designs');
        Schema::dropIfExists('mm_patrons');

        Schema::create('mm_patrons', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

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
            $table->string('design_type')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_product_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_name')->nullable();
            $table->string('unit_code')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id();
            $table->decimal('batch_size', 10, 3)->default(0);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_batch_materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->string('material_name')->nullable();
            $table->decimal('target_qty', 10, 3)->default(0);
            $table->decimal('actual_qty', 10, 3)->default(0);
            $table->decimal('deviation_quantity', 10, 3)->default(0);
            $table->unsignedBigInteger('uom_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('mixdesign_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->timestamp('dispatch_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_details_api_returns_customer_grade_mix_design_and_material_consumption(): void
    {
        DB::table('mm_patrons')->insert([
            ['id' => 1, 'legal_name' => 'ABC Builders', 'deleted_at' => null],
            ['id' => 2, 'legal_name' => 'XYZ Infra', 'deleted_at' => null],
        ]);

        DB::table('mm_concrete_grades')->insert([
            ['id' => 1, 'plant_id' => 2, 'name' => 'M30', 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 2, 'name' => 'M40', 'deleted_at' => null],
        ]);

        DB::table('mm_mix_designs')->insert([
            ['id' => 10, 'plant_id' => 2, 'design_name' => 'M30 Pump Mix', 'design_type' => 'M30', 'deleted_at' => null],
            ['id' => 20, 'plant_id' => 2, 'design_name' => 'M40 Premium Mix', 'design_type' => 'M40', 'deleted_at' => null],
        ]);

        DB::table('mm_product_units')->insert([
            ['id' => 1, 'unit_name' => 'Kilogram', 'unit_code' => 'kg', 'deleted_at' => null],
            ['id' => 2, 'unit_name' => 'Litre', 'unit_code' => 'ltr', 'deleted_at' => null],
        ]);

        DB::table('mm_batches')->insert([
            ['id' => 1001, 'batch_size' => 2.500, 'deleted_at' => null],
            ['id' => 1002, 'batch_size' => 3.000, 'deleted_at' => null],
            ['id' => 1003, 'batch_size' => 4.000, 'deleted_at' => null],
        ]);

        DB::table('mm_batch_materials')->insert([
            ['batch_id' => 1001, 'material_name' => 'Cement', 'target_qty' => 300.000, 'actual_qty' => 305.500, 'deviation_quantity' => 5.500, 'uom_id' => 1, 'deleted_at' => null],
            ['batch_id' => 1001, 'material_name' => 'Water', 'target_qty' => 120.000, 'actual_qty' => 118.000, 'deviation_quantity' => -2.000, 'uom_id' => 2, 'deleted_at' => null],
            ['batch_id' => 1002, 'material_name' => 'Cement', 'target_qty' => 310.000, 'actual_qty' => 312.000, 'deviation_quantity' => 2.000, 'uom_id' => 1, 'deleted_at' => null],
            ['batch_id' => 1002, 'material_name' => 'Water', 'target_qty' => 125.000, 'actual_qty' => 126.250, 'deviation_quantity' => 1.250, 'uom_id' => 2, 'deleted_at' => null],
            ['batch_id' => 1003, 'material_name' => 'Cement', 'target_qty' => 450.000, 'actual_qty' => 448.500, 'deviation_quantity' => -1.500, 'uom_id' => 1, 'deleted_at' => null],
        ]);

        DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 2,
                'customer_id' => 1,
                'mixdesign_id' => 10,
                'batch_id' => 1001,
                'dispatch_time' => '2026-05-10 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'customer_id' => 1,
                'mixdesign_id' => 10,
                'batch_id' => 1002,
                'dispatch_time' => '2026-05-11 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'customer_id' => 2,
                'mixdesign_id' => 20,
                'batch_id' => 1003,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 3,
                'customer_id' => 2,
                'mixdesign_id' => 20,
                'batch_id' => 1003,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/customer-details?from_date=2026-05-01&to_date=2026-05-18&plant_id=2&type=daily');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    [
                        'customer_id' => 1,
                        'customer_name' => 'ABC Builders',
                        'grade' => 'M30',
                        'mix_design' => [
                            'id' => 10,
                            'design_name' => 'M30 Pump Mix',
                        ],
                        'total_dispatch_count' => [
                            'qty' => 2,
                            'unit' => 'count',
                        ],
                        'material_consumption' => [
                            [
                                'material_name' => 'Cement',
                                'target_qty' => ['qty' => 610.0, 'unit' => 'kg'],
                                'actual_qty' => ['qty' => 617.5, 'unit' => 'kg'],
                                'deviation_qty' => ['qty' => 7.5, 'unit' => 'kg'],
                            ],
                            [
                                'material_name' => 'Water',
                                'target_qty' => ['qty' => 245.0, 'unit' => 'ltr'],
                                'actual_qty' => ['qty' => 244.25, 'unit' => 'ltr'],
                                'deviation_qty' => ['qty' => -0.75, 'unit' => 'ltr'],
                            ],
                        ],
                    ],
                    [
                        'customer_id' => 2,
                        'customer_name' => 'XYZ Infra',
                        'grade' => 'M40',
                        'mix_design' => [
                            'id' => 20,
                            'design_name' => 'M40 Premium Mix',
                        ],
                        'total_dispatch_count' => [
                            'qty' => 1,
                            'unit' => 'count',
                        ],
                    ],
                ],
            ]);
    }
}
