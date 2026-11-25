<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 16:33:51 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_batch_material_actuals', 'plant_id')) {
            Schema::table('mm_batch_material_actuals', function (Blueprint $table) {
                $table->unsignedBigInteger('plant_id')->nullable()->index();
            });
        }

        $parentPlant = DB::table('mm_batches')
            ->whereColumn('mm_batches.id', 'mm_batch_material_actuals.batch_id')
            ->whereNotNull('mm_batches.plant_id');

        DB::table('mm_batch_material_actuals')
            ->where(fn ($missing) => $missing->whereNull('plant_id')->orWhere('plant_id', 0))
            ->whereExists(clone $parentPlant)
            ->update(['plant_id' => $parentPlant->select('mm_batches.plant_id')]);
    }

    public function down(): void
    {
        // Some installations already had this column. Preserve it and its ownership data.
    }
};
