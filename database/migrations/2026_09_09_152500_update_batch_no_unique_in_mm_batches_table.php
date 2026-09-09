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
        // 1. Drop active_batch_no and its unique constraint if present
        if (Schema::hasColumn('mm_batches', 'active_batch_no')) {
            try {
                Schema::table('mm_batches', function (Blueprint $table) {
                    $table->dropUnique('mm_batches_plant_active_batch_no_unique');
                });
            } catch (\Throwable $e) {
                // Ignore if unique index doesn't exist
            }
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->dropColumn('active_batch_no');
            });
        }

        // 2. Add composite unique index directly on ['plant_id', 'batch_no']
        try {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->dropUnique('mm_batches_plant_batch_no_unique');
            });
        } catch (\Throwable $e) {
            // Ignore if unique index doesn't exist
        }

        Schema::table('mm_batches', function (Blueprint $table) {
            $table->unique(['plant_id', 'batch_no'], 'mm_batches_plant_batch_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropUnique('mm_batches_plant_batch_no_unique');
        });
    }
};
