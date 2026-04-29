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
        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->foreignId('truck_id')->nullable()->after('inward_no')->constrained('mm_machines')->nullOnDelete();
            $table->decimal('truck_loaded', 15, 2)->nullable()->after('truck_id');
            $table->decimal('truck_empty', 15, 2)->nullable()->after('truck_loaded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->dropForeign(['truck_id']);
            $table->dropColumn(['truck_id', 'truck_loaded', 'truck_empty']);
        });
    }
};
