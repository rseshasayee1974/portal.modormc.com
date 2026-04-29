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
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->decimal('empty_weight_truck', 15, 3)->nullable()->after('truck_id');
             $table->decimal('loaded_weight_truck', 15, 3)->nullable()->after('empty_weight_truck');
             $table->decimal('net_weight', 15, 3)->nullable()->after('loaded_weight_truck');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropColumn('empty_weight_truck');
        });
    }
};
