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
            if (!Schema::hasColumn('mm_quotations', 'sales_executive_id')) {
                $table->unsignedBigInteger('sales_executive_id')->nullable()->after('status');
                $table->foreign('sales_executive_id')->references('id')->on('mm_personnels')->nullOnDelete();
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_sales_orders', 'sales_executive_id')) {
                $table->unsignedBigInteger('sales_executive_id')->nullable()->after('status');
                $table->foreign('sales_executive_id')->references('id')->on('mm_personnels')->nullOnDelete();
            }
        });

        if (Schema::hasTable('mm_work_orders') && !Schema::hasColumn('mm_work_orders', 'sales_executive_id')) {
            Schema::table('mm_work_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_executive_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'sales_executive_id')) {
                $table->dropForeign(['sales_executive_id']);
                $table->dropColumn('sales_executive_id');
            }
        });

        Schema::table('mm_quotations', function (Blueprint $table) {
            if (Schema::hasColumn('mm_quotations', 'sales_executive_id')) {
                $table->dropForeign(['sales_executive_id']);
                $table->dropColumn('sales_executive_id');
            }
        });
    }
};
