<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mm_stock_exhaust_lines', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('stock_id')->constrained('mm_products');
            $table->string('issued_to', 255)->nullable()->change();
            $table->foreignId('vehicle_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_stock_exhaust_lines', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
            $table->string('issued_to', 255)->nullable(false)->change();
            $table->foreignId('vehicle_no')->nullable(false)->change();
        });
    }
};
