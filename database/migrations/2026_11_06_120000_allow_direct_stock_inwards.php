<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 10:16:45 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('order_item_id')->nullable()->change();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
        });
    }
    public function down(): void {
        if (DB::table('mm_purchase_order_history')->whereNull('order_id')->exists()) throw new RuntimeException('Direct inward records exist; preserve their history before reverting this migration.');
        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->unsignedBigInteger('order_item_id')->nullable(false)->change();
            $table->dropColumn('vendor_id');
        });
    }
};
