<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 17:08:49 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasActiveNumber = Schema::hasColumn('mm_batches', 'active_batch_no');
        if ($hasActiveNumber) {
            foreach (Schema::getIndexes('mm_batches') as $index) {
                if (in_array('active_batch_no', $index['columns'], true)) {
                    Schema::table('mm_batches', fn (Blueprint $table) => $table->dropIndex($index['name']));
                }
            }
            Schema::table('mm_batches', fn (Blueprint $table) => $table->dropColumn('active_batch_no'));
        }
        Schema::table('mm_batches', fn (Blueprint $table) => $table->string('batch_no', 40)->nullable()->change());
        if ($hasActiveNumber) {
            Schema::table('mm_batches', function (Blueprint $table) {
                $table->string('active_batch_no', 40)->nullable()
                    ->virtualAs('CASE WHEN deleted_at IS NULL THEN batch_no ELSE NULL END');
                $table->unique(['plant_id', 'active_batch_no'], 'mm_batches_plant_active_batch_no_unique');
            });
        }
    }

    public function down(): void
    {
        // B/P identifiers cannot safely be converted back to integers; retain the values.
    }
};
