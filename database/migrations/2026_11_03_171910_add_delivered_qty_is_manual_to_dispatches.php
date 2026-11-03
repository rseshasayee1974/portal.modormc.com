<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 17:19:10 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->boolean('delivered_qty_is_manual')->default(false));
        // Preserve historical delivery quantities that differ from their production batch.
        DB::table('mm_dispatches')->where('delivered_qty', '>', 0)->where(function ($query) {
            $query->whereNull('batch_id')->orWhereExists(function ($batch) {
                $batch->selectRaw('1')->from('mm_batches')->whereColumn('mm_batches.id', 'mm_dispatches.batch_id')
                    ->whereColumn('mm_batches.batch_size', '<>', 'mm_dispatches.delivered_qty');
            });
        })->update(['delivered_qty_is_manual' => true]);
    }

    public function down(): void
    {
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->dropColumn('delivered_qty_is_manual'));
    }
};
