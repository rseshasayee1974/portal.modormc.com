<?php

/*
Author: ragul-onemodo
Created: 2026-11-25 16:34:35 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'mm_batch_material_actuals';
        foreach (['created_by', 'updated_by', 'deleted_by', 'deleted_at'] as $column) {
            if (Schema::hasColumn($tableName, $column)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                if ($column === 'deleted_at') {
                    $table->softDeletes();
                } else {
                    $table->unsignedBigInteger($column)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Preserve audit records, including fields present before this migration.
    }
};
