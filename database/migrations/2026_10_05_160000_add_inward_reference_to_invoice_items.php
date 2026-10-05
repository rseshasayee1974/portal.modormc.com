<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_history_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('mm_invoice_items', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_history_id']);
            $table->dropColumn('purchase_order_history_id');
        });
    }
};
