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
            if (!Schema::hasColumn('mm_sales_orders', 'rate')) {
                $table->decimal('rate', 17, 3)->nullable()->default(0)->after('total_qty');
            }
            if (!Schema::hasColumn('mm_sales_orders', 'tax_id')) {
                $table->unsignedBigInteger('tax_id')->nullable()->after('rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'rate')) {
                $table->dropColumn('rate');
            }
            if (Schema::hasColumn('mm_sales_orders', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
        });
    }
};
