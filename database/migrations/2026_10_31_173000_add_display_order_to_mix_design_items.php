<?php

/*
Author: ragul-onemodo
Created: 2026-10-31 17:26:51 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_mix_design_items', function (Blueprint $table) {
            $table->unsignedInteger('display_order')->default(0);
            $table->index(['mix_design_id', 'display_order'], 'mix_items_display_order_index');
        });

        // Preserve the grade's current order for existing mix designs.
        DB::statement('UPDATE mm_mix_design_items SET display_order = COALESCE((
            SELECT MIN(gi.display_order) FROM mm_concrete_grade_items gi
            JOIN mm_mix_designs md ON md.concrete_grade_id = gi.concrete_grade_id
                AND md.plant_id = gi.plant_id
            WHERE md.id = mm_mix_design_items.mix_design_id
                AND gi.product_id = mm_mix_design_items.product_id
                AND gi.deleted_at IS NULL
        ), (
            SELECT CASE WHEN MAX(gi.display_order) = 4294967295 THEN 4294967295
                ELSE COALESCE(MAX(gi.display_order), 0) + 1 END
            FROM mm_concrete_grade_items gi
            JOIN mm_mix_designs md ON md.concrete_grade_id = gi.concrete_grade_id
                AND md.plant_id = gi.plant_id
            WHERE md.id = mm_mix_design_items.mix_design_id AND gi.deleted_at IS NULL
        ))');
    }

    public function down(): void
    {
        Schema::table('mm_mix_design_items', function (Blueprint $table) {
            $table->dropIndex('mix_items_display_order_index');
            $table->dropColumn('display_order');
        });
    }
};
