<?php
/*
Author: Cipher
Created: 2026-11-04 16:52:40 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_personnel_user_rels', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_personnel_user_rels', 'deleted_at')) $table->softDeletes();
            if (!Schema::hasIndex('mm_personnel_user_rels', 'personnel_user_rels_personnel_index')) {
                $table->index('personnel_id', 'personnel_user_rels_personnel_index');
            }
        });
        if (Schema::hasIndex('mm_personnel_user_rels', 'mm_user_personnel_personnel_id_unique')) {
            Schema::table('mm_personnel_user_rels', fn (Blueprint $table) => $table->dropUnique('mm_user_personnel_personnel_id_unique'));
        }
        if (Schema::hasColumn('mm_personnel_user_rels', 'active_personnel_id')) {
            if (Schema::hasIndex('mm_personnel_user_rels', 'personnel_user_rels_active_unique')) {
                Schema::table('mm_personnel_user_rels', fn (Blueprint $table) => $table->dropUnique('personnel_user_rels_active_unique'));
            }
            Schema::table('mm_personnel_user_rels', fn (Blueprint $table) => $table->dropColumn('active_personnel_id'));
        }
        Schema::table('mm_personnel_user_rels', function (Blueprint $table) {
            $column = $table->unsignedBigInteger('active_personnel_id');
            $expression = 'CASE WHEN deleted_at IS NULL THEN personnel_id ELSE NULL END';
            if (DB::getDriverName() === 'mysql') $column->storedAs($expression);
            else $column->virtualAs($expression);
            $table->unique('active_personnel_id', 'personnel_user_rels_active_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('mm_personnel_user_rels')->select('personnel_id')->groupBy('personnel_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot remove soft deletes while historical links exist for the same personnel. Preserve or reconcile those records before rollback.');
        }
        Schema::table('mm_personnel_user_rels', function (Blueprint $table) {
            $table->dropUnique('personnel_user_rels_active_unique');
            $table->dropColumn('active_personnel_id');
        });
        Schema::table('mm_personnel_user_rels', function (Blueprint $table) {
            $table->unique('personnel_id', 'mm_user_personnel_personnel_id_unique');
            $table->dropIndex('personnel_user_rels_personnel_index');
            $table->dropSoftDeletes();
        });
    }
};
