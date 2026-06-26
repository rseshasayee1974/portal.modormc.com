<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DispatchSalesAmountApiTest extends TestCase
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
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_dispatch_sales_amount_api_returns_cash_and_credit_totals(): void
    {
        \DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 1,
                'payment_mode' => 'cash',
                'load_total_amount' => 1000.00,
                'net_weight' => 10.50,
                'delivered_qty' => 5.25,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 1,
                'payment_mode' => 'credit',
                'load_total_amount' => 2500.00,
                'net_weight' => 21.75,
                'delivered_qty' => 9.50,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 1,
                'payment_mode' => 'cash',
                'load_total_amount' => 500.00,
                'net_weight' => 5.00,
                'delivered_qty' => 2.50,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 2,
                'payment_mode' => 'credit',
                'load_total_amount' => 9999.00,
                'net_weight' => 99.00,
                'delivered_qty' => 99.00,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/dispatch-sales-amount?plant_id=1');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'total_dispatch_sales_amount' => 4000.0,
                    'cash_sales_amount' => 1500.0,
                    'credit_sales_amount' => 2500.0,
                    'cash_dispatch_count' => 2,
                    'credit_dispatch_count' => 1,
                    'cash_quantity_mt' => 15.5,
                    'credit_quantity_mt' => 21.75,
                    'cash_quantity_cft' => 7.75,
                    'credit_quantity_cft' => 9.5,
                ],
            ]);
    }

    public function test_sales_summary_uses_lowercase_payment_modes(): void
    {
        \DB::table('mm_dispatches')->insert([
            [
                'plant_id' => 3,
                'payment_mode' => 'cash',
                'load_total_amount' => 1200.00,
                'net_weight' => 8.00,
                'delivered_qty' => 4.00,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plant_id' => 3,
                'payment_mode' => 'credit',
                'load_total_amount' => 800.00,
                'net_weight' => 6.00,
                'delivered_qty' => 3.00,
                'dispatch_time' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/dashboard/sales-summary?plant_id=3');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'total_sales' => 2000.0,
                    'cash_sales' => [
                        'amount' => 1200.0,
                        '%' => 60.0,
                    ],
                    'credit_sales' => [
                        'amount' => 800.0,
                        '%' => 40.0,
                    ],
                ],
            ]);
    }
}
