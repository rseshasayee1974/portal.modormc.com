<?php
/*
Author: ragul-onemodo
Created: 2026-11-03 17:28:30 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->decimal('delivered_qty', 10, 3)->nullable()->default(null)->change());
        // Automatic batch quantities become null; keep explicit quantities, including zero.
        DB::table('mm_dispatches')->where('delivered_qty_is_manual', false)
            ->whereNotNull('batch_id')->update(['delivered_qty' => null]);
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->dropColumn('delivered_qty_is_manual'));
    }

    public function down(): void
    {
        Schema::table('mm_dispatches', fn (Blueprint $table) => $table->boolean('delivered_qty_is_manual')->default(false));
        DB::table('mm_dispatches')->whereNotNull('delivered_qty')->update(['delivered_qty_is_manual' => true]);
        DB::table('mm_dispatches')->whereNull('delivered_qty')->update([
            'delivered_qty' => DB::raw('COALESCE((SELECT batch_size FROM mm_batches WHERE mm_batches.id = mm_dispatches.batch_id), 0)'),
        ]);
    }
};
