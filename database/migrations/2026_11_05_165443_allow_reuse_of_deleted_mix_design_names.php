<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 16:54:43 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_mix_designs', 'active_unique')) {
            Schema::table('mm_mix_designs', function (Blueprint $table) {
                $column = $table->unsignedTinyInteger('active_unique')->nullable();
                $expression = 'CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END';
                if (DB::getDriverName() === 'mysql') $column->storedAs($expression);
                else $column->virtualAs($expression);
            });
        }
        if (!Schema::hasIndex('mm_mix_designs', 'mm_mix_designs_active_name_unique')) {
            Schema::table('mm_mix_designs', fn (Blueprint $table) => $table->unique(
                ['plant_id', 'partner_id', 'design_name', 'active_unique'], 'mm_mix_designs_active_name_unique'
            ));
        }
        if (Schema::hasIndex('mm_mix_designs', 'mm_mix_designs_plant_id_partner_id_design_name_unique')) {
            Schema::table('mm_mix_designs', fn (Blueprint $table) => $table->dropUnique('mm_mix_designs_plant_id_partner_id_design_name_unique'));
        }
    }

    public function down(): void
    {
        // Restore the old constraint first; fail without removing protection if
        // a deleted design's name has since been reused.
        if (!Schema::hasIndex('mm_mix_designs', 'mm_mix_designs_plant_id_partner_id_design_name_unique')) {
            Schema::table('mm_mix_designs', fn (Blueprint $table) => $table->unique(
                ['plant_id', 'partner_id', 'design_name'], 'mm_mix_designs_plant_id_partner_id_design_name_unique'
            ));
        }
        Schema::table('mm_mix_designs', function (Blueprint $table) {
            $table->dropUnique('mm_mix_designs_active_name_unique');
            $table->dropColumn('active_unique');
        });
    }
};
