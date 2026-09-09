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
        // 1. Drop active_batch_no column if partially created from a previous run
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

        // 2. Stored generated column: supports unique index in MySQL
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->string('active_batch_no')
                ->storedAs("CASE WHEN deleted_at IS NULL THEN batch_no ELSE NULL END")
                ->nullable()
                ->after('batch_no');

            // Unique index per plant only among active (non-deleted) records
            $table->unique(['plant_id', 'active_batch_no'], 'mm_batches_plant_active_batch_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropUnique('mm_batches_plant_active_batch_no_unique');
            $table->dropColumn('active_batch_no');
        });
    }
};
