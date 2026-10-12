<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 16:47:17 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_batches')) {
            return;
        }

        if (!Schema::hasColumn('mm_batches', 'active_batch_no')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->integer('active_batch_no')->nullable()
                    ->virtualAs('CASE WHEN deleted_at IS NULL THEN batch_no ELSE NULL END');
            });
        }

        if (!Schema::hasIndex('mm_batches', 'mm_batches_plant_active_batch_no_unique')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->unique(['plant_id', 'active_batch_no'], 'mm_batches_plant_active_batch_no_unique');
            });
        }

        if (Schema::hasIndex('mm_batches', 'mm_batches_plant_batch_no_unique')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->dropUnique('mm_batches_plant_batch_no_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('mm_batches')) {
            return;
        }

        // Reinstating the old index must succeed before removing active-only protection.
        if (!Schema::hasIndex('mm_batches', 'mm_batches_plant_batch_no_unique')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->unique(['plant_id', 'batch_no'], 'mm_batches_plant_batch_no_unique');
            });
        }

        if (Schema::hasIndex('mm_batches', 'mm_batches_plant_active_batch_no_unique')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->dropUnique('mm_batches_plant_active_batch_no_unique');
            });
        }
        if (Schema::hasColumn('mm_batches', 'active_batch_no')) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->dropColumn('active_batch_no');
            });
        }
    }
};
