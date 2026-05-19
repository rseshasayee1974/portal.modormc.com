<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispatchDetailsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_dispatches');
        Schema::dropIfExists('mm_batches');
        Schema::dropIfExists('mm_machines');

        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id();
            $table->decimal('batch_size', 10, 3)->default(0);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_machines', function (Blueprint $table) {
            $table->id();
            $table->string('registration')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('delivered_qty', 10, 3)->default(0);
            $table->decimal('net_weight', 15, 3)->nullable();
            $table->timestamp('dispatch_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_dispatch_details_api_returns_truck_wise_dispatch_count_and_batch_size(): void
    {
        DB::table('mm_machines')->insert([
            ['id' => 1, 'registration' => 'MH12AB1234', 'deleted_at' => null],
            ['id' => 2, 'registration' => 'MH14CD5678', 'deleted_at' => null],
        ]);

        DB::table('mm_batches')->insert([
            ['id' => 11, 'batch_size' => 2.500, 'deleted_at' => null],
            ['id' => 12, 'batch_size' => 3.000, 'deleted_at' => null],
            ['id' => 13, 'batch_size' => 1.500, 'deleted_at' => null],
        ]);

        DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 2,
                'truck_id' => 1,
                'batch_id' => 11,
                'delivered_qty' => 2.500,
                'net_weight' => 10.250,
                'dispatch_time' => '2026-05-10 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'truck_id' => 1,
                'batch_id' => 12,
                'delivered_qty' => 3.000,
                'net_weight' => 11.750,
                'dispatch_time' => '2026-05-11 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'truck_id' => 2,
                'batch_id' => 13,
                'delivered_qty' => 1.500,
                'net_weight' => 6.500,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 3,
                'truck_id' => 2,
                'batch_id' => 13,
                'delivered_qty' => 1.500,
                'net_weight' => 6.500,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/dispatch-details?from_date=2026-05-01&to_date=2026-05-18&plant_id=2&type=daily');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    [
                        'truck_id' => 1,
                        'truck_registration' => 'MH12AB1234',
                        'total_dispatch_count' => [
                            'qty' => 2,
                            'unit' => 'count',
                        ],
                        'total_batch_size' => [
                            'qty' => 5.5,
                            'unit' => 'cbm',
                        ],
                        'total_qty' => [
                            'cft' => [
                                'qty' => 194.231,
                                'unit' => 'cft',
                            ],
                            'mtr' => [
                                'qty' => 22.0,
                                'unit' => 'mtr',
                            ],
                        ],
                    ],
                    [
                        'truck_id' => 2,
                        'truck_registration' => 'MH14CD5678',
                        'total_dispatch_count' => [
                            'qty' => 1,
                            'unit' => 'count',
                        ],
                        'total_batch_size' => [
                            'qty' => 1.5,
                            'unit' => 'cbm',
                        ],
                        'total_qty' => [
                            'cft' => [
                                'qty' => 52.972,
                                'unit' => 'cft',
                            ],
                            'mtr' => [
                                'qty' => 6.5,
                                'unit' => 'mtr',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
