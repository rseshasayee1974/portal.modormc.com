<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Deduplicate mm_leave_types table records
        if (Schema::hasTable('mm_leave_types')) {
            $duplicateTypes = DB::table('mm_leave_types')
                ->select('name', DB::raw('MIN(id) as min_id'), DB::raw('GROUP_CONCAT(id) as all_ids'))
                ->groupBy('name')
                ->get();

            foreach ($duplicateTypes as $row) {
                $canonicalId = $row->min_id;
                $allIds = array_map('intval', explode(',', $row->all_ids));
                $duplicateIds = array_diff($allIds, [$canonicalId]);

                if (!empty($duplicateIds)) {
                    // Reassign foreign keys in mm_leave_applications
                    if (Schema::hasTable('mm_leave_applications')) {
                        DB::table('mm_leave_applications')
                            ->whereIn('leave_type_id', $duplicateIds)
                            ->update(['leave_type_id' => $canonicalId]);
                    }

                    // Reassign foreign keys in mm_employee_leave_balances if exists
                    if (Schema::hasTable('mm_employee_leave_balances')) {
                        DB::table('mm_employee_leave_balances')
                            ->whereIn('leave_type_id', $duplicateIds)
                            ->update(['leave_type_id' => $canonicalId]);
                    }

                    // Delete duplicate rows
                    DB::table('mm_leave_types')
                        ->whereIn('id', $duplicateIds)
                        ->delete();
                }
            }
        }

        // 2. Drop plant_id from mm_leave_types if it exists
        if (Schema::hasColumn('mm_leave_types', 'plant_id')) {
            try {
                Schema::table('mm_leave_types', function (Blueprint $table) {
                    $table->dropForeign(['plant_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('mm_leave_types', function (Blueprint $table) {
                    $table->dropColumn('plant_id');
                });
            } catch (\Throwable $e) {}
        }

        // 3. Add unique constraint on name if not already present
        try {
            Schema::table('mm_leave_types', function (Blueprint $table) {
                $table->unique('name');
            });
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('mm_leave_types', function (Blueprint $table) {
                $table->dropUnique(['name']);
            });
        } catch (\Throwable $e) {}
    }
};
