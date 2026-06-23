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
            $table->index(['plant_id', 'status', 'start_time'], 'idx_batches_plant_status_time');
        });

        Schema::table('mm_batch_materials', function (Blueprint $table) {
            $table->index(['batch_id', 'product_id'], 'idx_batch_materials_batch_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropIndex('idx_batches_plant_status_time');
        });

        Schema::table('mm_batch_materials', function (Blueprint $table) {
            $table->dropIndex('idx_batch_materials_batch_product');
        });
    }
};