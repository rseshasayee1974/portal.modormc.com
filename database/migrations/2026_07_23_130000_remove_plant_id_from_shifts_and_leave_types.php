<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop plant_id from mm_shifts
        if (Schema::hasColumn('mm_shifts', 'plant_id')) {
            try {
                Schema::table('mm_shifts', function (Blueprint $table) {
                    $table->dropForeign(['plant_id']);
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_shifts', function (Blueprint $table) {
                    $table->dropColumn('plant_id');
                });
            } catch (\Exception $e) {}
        }

        // Drop plant_id from mm_leave_types
        if (Schema::hasColumn('mm_leave_types', 'plant_id')) {
            try {
                Schema::table('mm_leave_types', function (Blueprint $table) {
                    $table->dropForeign(['plant_id']);
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_leave_types', function (Blueprint $table) {
                    $table->dropColumn('plant_id');
                });
            } catch (\Exception $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('mm_shifts', 'plant_id')) {
            Schema::table('mm_shifts', function (Blueprint $table) {
                $table->foreignId('plant_id')->nullable()->after('id')->constrained('mm_plants')->onDelete('cascade');
            });
        }

        if (!Schema::hasColumn('mm_leave_types', 'plant_id')) {
            Schema::table('mm_leave_types', function (Blueprint $table) {
                $table->foreignId('plant_id')->nullable()->after('id')->constrained('mm_plants')->onDelete('cascade');
            });
        }
    }
};
