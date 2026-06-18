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
            if (Schema::hasColumn('mm_sales_orders', 'converted_by_role')) {
                $table->dropColumn('converted_by_role');
            }
            if (Schema::hasColumn('mm_sales_orders', 'converted_by_department')) {
                $table->dropColumn('converted_by_department');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_sales_orders', 'converted_by_role')) {
                $table->string('converted_by_role')->nullable()->after('converted_by_user_id');
            }
            if (!Schema::hasColumn('mm_sales_orders', 'converted_by_department')) {
                $table->string('converted_by_department')->nullable()->after('converted_by_role');
            }
        });
    }
};
