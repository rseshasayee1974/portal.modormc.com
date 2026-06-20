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
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->change();
            $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
        });

        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->change();
            $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
        });
    }
};
