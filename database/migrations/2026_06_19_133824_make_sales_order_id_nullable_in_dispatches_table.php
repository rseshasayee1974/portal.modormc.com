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
        try {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->dropForeign(['sales_order_id']);
            });
        } catch (\Exception $e) {
            // ignore if foreign key doesn't exist
        }

        if (Schema::hasColumn('mm_dispatches', 'sales_order_id')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->change();
            });
        } else {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_order_id')->nullable();
            });
        }

        try {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->foreign('sales_order_id')->references('id')->on('mm_sales_orders')->cascadeOnDelete();
            });
        } catch (\Exception $e) {
            // ignore if foreign key already exists or cannot be created
        }
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
