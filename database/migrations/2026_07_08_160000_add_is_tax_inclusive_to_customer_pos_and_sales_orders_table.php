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
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->boolean('is_tax_inclusive')->default(false)->after('pump_type');
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            $table->boolean('is_tax_inclusive')->default(false)->after('pump_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->dropColumn('is_tax_inclusive');
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            $table->dropColumn('is_tax_inclusive');
        });
    }
};
