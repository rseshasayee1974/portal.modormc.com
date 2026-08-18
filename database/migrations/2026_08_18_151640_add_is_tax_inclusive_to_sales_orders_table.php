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
        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_sales_orders', 'is_tax_inclusive')) {
                $table->boolean('is_tax_inclusive')->default(false)->after('tax_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'is_tax_inclusive')) {
                $table->dropColumn('is_tax_inclusive');
            }
        });
    }
};
