<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 15:34:23 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mm_concrete_batching_schedules')
            && !Schema::hasColumn('mm_concrete_batching_schedules', 'batch_id')) {
            Schema::table('mm_concrete_batching_schedules', function (Blueprint $table) {
                $table->foreignId('batch_id')->nullable()->after('sales_order_id')
                    ->constrained('mm_batches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mm_concrete_batching_schedules')
            && Schema::hasColumn('mm_concrete_batching_schedules', 'batch_id')) {
            foreach (Schema::getForeignKeys('mm_concrete_batching_schedules') as $foreignKey) {
                if ($foreignKey['columns'] === ['batch_id']) {
                    Schema::table('mm_concrete_batching_schedules', function (Blueprint $table) use ($foreignKey) {
                        $table->dropForeign($foreignKey['name']);
                    });
                }
            }

            Schema::table('mm_concrete_batching_schedules', function (Blueprint $table) {
                $table->dropColumn('batch_id');
            });
        }
    }
};
