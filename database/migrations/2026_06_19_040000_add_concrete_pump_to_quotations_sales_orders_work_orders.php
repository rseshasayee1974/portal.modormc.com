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
            if (!Schema::hasColumn('mm_quotations', 'concrete_pump')) {
                $table->string('concrete_pump')->nullable()->after('sales_executive_id');
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_sales_orders', 'concrete_pump')) {
                $table->string('concrete_pump')->nullable()->after('sales_executive_id');
            }
        });

        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_work_orders', 'concrete_pump')) {
                $table->string('concrete_pump')->nullable()->after('status');
            }
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_dispatches', 'concrete_pump')) {
                $table->string('concrete_pump')->nullable()->after('sales_executive_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('mm_quotations', 'concrete_pump')) {
                $table->dropColumn('concrete_pump');
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'concrete_pump')) {
                $table->dropColumn('concrete_pump');
            }
        });

        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_work_orders', 'concrete_pump')) {
                $table->dropColumn('concrete_pump');
            }
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('mm_dispatches', 'concrete_pump')) {
                $table->dropColumn('concrete_pump');
            }
        });
    }
};
