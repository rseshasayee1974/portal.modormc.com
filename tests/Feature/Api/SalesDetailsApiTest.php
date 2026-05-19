<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesDetailsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_dispatches');

        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->string('payment_mode')->nullable();
            $table->decimal('load_total_amount', 17, 2)->default(0);
            $table->decimal('net_weight', 15, 3)->nullable();
            $table->decimal('delivered_qty', 10, 3)->default(0);
            $table->timestamp('dispatch_time')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_sales_details_api_returns_cash_and_credit_sales_with_cbm_cft_and_mtr(): void
    {
        DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 2,
                'payment_mode' => 'cash',
                'load_total_amount' => 1000.00,
                'net_weight' => 10.500,
                'delivered_qty' => 2.500,
                'dispatch_time' => '2026-05-10 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'payment_mode' => 'cash',
                'load_total_amount' => 500.00,
                'net_weight' => 5.250,
                'delivered_qty' => 1.250,
                'dispatch_time' => '2026-05-11 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'payment_mode' => 'credit',
                'load_total_amount' => 2500.00,
                'net_weight' => 20.750,
                'delivered_qty' => 4.000,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 3,
                'payment_mode' => 'credit',
                'load_total_amount' => 9999.00,
                'net_weight' => 99.000,
                'delivered_qty' => 9.000,
                'dispatch_time' => '2026-05-12 10:00:00',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/sales-details?from_date=2026-05-01&to_date=2026-05-18&plant_id=2&type=daily');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'cash_sales' => [
                        'sales_amount' => [
                            'qty' => 1500.0,
                            'unit' => 'amount',
                        ],
                        'quantity' => [
                            'cbm' => [
                                'qty' => 3.75,
                                'unit' => 'cbm',
                            ],
                            'cft' => [
                                'qty' => 132.43,
                                'unit' => 'cft',
                            ],
                            'mtr' => [
                                'qty' => 15.75,
                                'unit' => 'mtr',
                            ],
                        ],
                    ],
                    'credit_sales' => [
                        'sales_amount' => [
                            'qty' => 2500.0,
                            'unit' => 'amount',
                        ],
                        'quantity' => [
                            'cbm' => [
                                'qty' => 4.0,
                                'unit' => 'cbm',
                            ],
                            'cft' => [
                                'qty' => 141.259,
                                'unit' => 'cft',
                            ],
                            'mtr' => [
                                'qty' => 20.75,
                                'unit' => 'mtr',
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
