<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispatchBatchingSummaryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_dispatches');
        Schema::dropIfExists('mm_batches');

        Schema::create('mm_batches', function (Blueprint $table) {
            $table->id();
            $table->decimal('batch_size', 10, 3)->default(0);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->decimal('net_weight', 15, 3)->nullable();
            $table->decimal('delivered_qty', 10, 3)->default(0);
            $table->timestamp('dispatch_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_dispatch_batching_summary_returns_requested_metrics(): void
    {
        DB::table('mm_batches')->insert([
            ['id' => 1, 'batch_size' => 2.500, 'deleted_at' => null],
            ['id' => 2, 'batch_size' => 3.000, 'deleted_at' => null],
            ['id' => 3, 'batch_size' => 1.500, 'deleted_at' => now()],
        ]);

        DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 2,
                'batch_id' => 1,
                'net_weight' => 11.250,
                'delivered_qty' => 2.500,
                'dispatch_time' => '2026-05-10 09:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'batch_id' => 2,
                'net_weight' => 13.750,
                'delivered_qty' => 3.000,
                'dispatch_time' => '2026-05-11 09:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'batch_id' => 3,
                'net_weight' => 8.000,
                'delivered_qty' => 1.500,
                'dispatch_time' => '2026-05-12 09:00:00',
                'deleted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 3,
                'batch_id' => 1,
                'net_weight' => 99.000,
                'delivered_qty' => 9.000,
                'dispatch_time' => '2026-05-13 09:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/dispatch-batching-summary?plant_id=2&from_date=2026-05-01&to_date=2026-05-18&type=daily');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'total_dispatch_batch_size' => [
                        'qty' => 5.5,
                        'unit' => 'cbm',
                    ],
                    'total_dispatch_net_quantity' => [
                        'mtr' => [
                            'qty' => 25.0,
                            'unit' => 'mtr',
                        ],
                        'cft' => [
                            'qty' => 194.231,
                            'unit' => 'cft',
                        ],
                    ],
                    'total_batching_count' => [
                        'qty' => 2,
                        'unit' => 'count',
                    ],
                ],
            ]);
    }
}
