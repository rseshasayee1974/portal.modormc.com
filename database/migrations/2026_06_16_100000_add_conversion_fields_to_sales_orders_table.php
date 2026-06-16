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
            if (!Schema::hasColumn('mm_sales_orders', 'converted_by_user_id')) {
                $table->foreignId('converted_by_user_id')->nullable()->after('status')->constrained('mm_users')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_sales_orders', 'converted_by_role')) {
                $table->string('converted_by_role')->nullable()->after('converted_by_user_id');
            }
            if (!Schema::hasColumn('mm_sales_orders', 'converted_by_department')) {
                $table->string('converted_by_department')->nullable()->after('converted_by_role');
            }
        });

        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_work_orders', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->after('id')->constrained('mm_sales_orders')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_work_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_work_orders', 'sales_order_id')) {
                $table->dropForeign(['sales_order_id']);
                $table->dropColumn('sales_order_id');
            }
        });

        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('mm_sales_orders', 'converted_by_user_id')) {
                $table->dropForeign(['converted_by_user_id']);
                $table->dropColumn('converted_by_user_id');
            }
            if (Schema::hasColumn('mm_sales_orders', 'converted_by_role')) {
                $table->dropColumn('converted_by_role');
            }
            if (Schema::hasColumn('mm_sales_orders', 'converted_by_department')) {
                $table->dropColumn('converted_by_department');
            }
        });
    }
};
