<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop plant_id from mm_departments
        if (Schema::hasColumn('mm_departments', 'plant_id')) {
            try {
                Schema::table('mm_departments', function (Blueprint $table) {
                    $table->dropForeign(['plant_id']);
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_departments', function (Blueprint $table) {
                    $table->dropColumn('plant_id');
                });
            } catch (\Exception $e) {}
        }

        // Drop plant_id from mm_designations
        if (Schema::hasColumn('mm_designations', 'plant_id')) {
            try {
                Schema::table('mm_designations', function (Blueprint $table) {
                    $table->dropForeign(['plant_id']);
                });
            } catch (\Exception $e) {}
            
            try {
                Schema::table('mm_designations', function (Blueprint $table) {
                    $table->dropColumn('plant_id');
                });
            } catch (\Exception $e) {}
        }
    }

    public function down(): void
    {
        Schema::table('mm_departments', function (Blueprint $table) {
            $table->foreignId('plant_id')->after('id')->constrained('mm_plants')->onDelete('cascade');
        });

        Schema::table('mm_designations', function (Blueprint $table) {
            $table->foreignId('plant_id')->after('id')->constrained('mm_plants')->onDelete('cascade');
        });
    }
};
