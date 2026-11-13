<?php
/*
Author: ragul-onemodo
Created: 2026-11-13 15:38:36 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_designations', 'plant_id')) {
            Schema::table('mm_designations', function (Blueprint $table) {
                $table->foreignId('plant_id')->nullable()->index()->constrained('mm_plants');
            });
        }

        DB::transaction(function () {
            $plantIds = DB::table('mm_plants')->orderBy('id')->pluck('id');
            if ($plantIds->isEmpty()) {
                return;
            }

            // Each plant previously shared the same catalog. Keep its personnel
            // linked to the equivalent plant-owned entry, including archived rows.
            DB::table('mm_designations')->whereNull('plant_id')->chunkById(100, function ($rows) use ($plantIds) {
                foreach ($rows as $row) {
                    DB::table('mm_designations')->where('id', $row->id)->update(['plant_id' => $plantIds->first()]);
                    $copy = (array) $row;
                    unset($copy['id']);
                    foreach ($plantIds->skip(1) as $plantId) {
                        $copy['plant_id'] = $plantId;
                        $id = DB::table('mm_designations')->insertGetId($copy);
                        DB::table('mm_personnels')->where('designation_id', $row->id)->where('plant_id', $plantId)
                            ->update(['designation_id' => $id]);
                    }
                }
            });
        });
    }

    public function down(): void
    {
        Schema::table('mm_designations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_id');
        });
    }
};
