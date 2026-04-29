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
        Schema::table('mm_purchase_order_items', function (Blueprint $table) {
            $table->decimal('total_discount', 17, 2)->default(0)->after('discount_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });
    }
};
