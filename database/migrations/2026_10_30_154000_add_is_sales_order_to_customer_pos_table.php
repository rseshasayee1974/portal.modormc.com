<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 15:40:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mm_customer_pos', 'is_sales_order')) {
            return;
        }

        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_sales_order')->default(0);
        });

        // Preserve conversion history, including subsequently soft-deleted orders.
        DB::table('mm_customer_pos')->whereIn('id', function ($query) {
            $query->select('customer_po_id')->from('mm_sales_orders')->whereNotNull('customer_po_id');
        })->update(['is_sales_order' => 1]);
    }

    public function down(): void
    {
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->dropColumn('is_sales_order');
        });
    }
};
