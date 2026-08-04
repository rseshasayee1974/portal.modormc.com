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
        Schema::table('mm_quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_quotations', 'pump_type')) {
                $table->string('pump_type')->nullable()->after('sales_executive_id');
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_sales_orders', 'pump_type')) {
                $table->string('pump_type')->nullable()->after('sales_executive_id');
            }
        });

        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_work_orders', 'pump_type')) {
                $table->string('pump_type')->nullable()->after('status');
            }
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_dispatches', 'pump_type')) {
                $table->string('pump_type')->nullable()->after('sales_executive_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('mm_quotations', 'pump_type')) {
                $table->dropColumn('pump_type');
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'pump_type')) {
                $table->dropColumn('pump_type');
            }
        });

        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_work_orders', 'pump_type')) {
                $table->dropColumn('pump_type');
            }
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('mm_dispatches', 'pump_type')) {
                $table->dropColumn('pump_type');
            }
        });
    }
};
