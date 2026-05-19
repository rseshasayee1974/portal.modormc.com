<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockDetailsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Schema::dropIfExists('mm_quantity');
        Schema::dropIfExists('mm_products');
        Schema::dropIfExists('mm_product_units');

        Schema::create('mm_product_units', function (Blueprint $table) {
            $table->id();
            $table->string('unit_name')->nullable();
            $table->string('unit_code')->nullable();
        });

        Schema::create('mm_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('title')->nullable();
            $table->string('code')->nullable();
            $table->decimal('stock_alert', 15, 2)->nullable()->default(0);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('mm_quantity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function test_stock_details_api_returns_current_stock_from_products_and_quantity_tables(): void
    {
        DB::table('mm_product_units')->insert([
            ['id' => 1, 'unit_name' => 'Kilogram', 'unit_code' => 'KG'],
            ['id' => 2, 'unit_name' => 'Liter', 'unit_code' => 'LTR'],
        ]);

        DB::table('mm_products')->insert([
            ['id' => 11, 'plant_id' => 2, 'unit_id' => 1, 'title' => 'Cement', 'code' => 'CEM001', 'stock_alert' => 50, 'deleted_at' => null],
            ['id' => 12, 'plant_id' => 2, 'unit_id' => 2, 'title' => 'Water', 'code' => 'WAT001', 'stock_alert' => 100, 'deleted_at' => null],
            ['id' => 13, 'plant_id' => 3, 'unit_id' => 1, 'title' => 'Sand', 'code' => 'SND001', 'stock_alert' => 25, 'deleted_at' => null],
        ]);

        DB::table('mm_quantity')->insert([
            ['plant_id' => 2, 'product_id' => 11, 'quantity' => 40.50, 'deleted_at' => null],
            ['plant_id' => 2, 'product_id' => 11, 'quantity' => 20.00, 'deleted_at' => null],
            ['plant_id' => 2, 'product_id' => 12, 'quantity' => 80.00, 'deleted_at' => null],
            ['plant_id' => 3, 'product_id' => 13, 'quantity' => 60.00, 'deleted_at' => null],
        ]);

        $response = $this->getJson('/api/dashboard/stock-details?plant_id=2');

        $response
            ->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    [
                        'product_id' => 11,
                        'product_name' => 'Cement',
                        'product_code' => 'CEM001',
                        'current_stock' => [
                            'qty' => 60.5,
                            'unit' => 'KG',
                        ],
                        'stock_alert' => [
                            'qty' => 50.0,
                            'unit' => 'KG',
                        ],
                        'stock_status' => [
                            'in_stock' => true,
                            'below_alert' => false,
                        ],
                    ],
                    [
                        'product_id' => 12,
                        'product_name' => 'Water',
                        'product_code' => 'WAT001',
                        'current_stock' => [
                            'qty' => 80.0,
                            'unit' => 'LTR',
                        ],
                        'stock_alert' => [
                            'qty' => 100.0,
                            'unit' => 'LTR',
                        ],
                        'stock_status' => [
                            'in_stock' => true,
                            'below_alert' => true,
                        ],
                    ],
                ],
            ]);
    }
}
