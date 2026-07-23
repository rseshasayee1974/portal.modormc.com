<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First drop foreign key constraints if they exist, then modify column to nullable, then add foreign key back with nullOnDelete
        Schema::table('mm_leave_types', function (Blueprint $table) {
            try {
                $table->dropForeign(['plant_id']);
            } catch (\Exception $e) {}
        });

        Schema::table('mm_leave_types', function (Blueprint $table) {
            $table->foreignId('plant_id')->nullable()->change();
        });

        Schema::table('mm_leave_types', function (Blueprint $table) {
            $table->foreign('plant_id')->references('id')->on('mm_plants')->nullOnDelete();
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            try {
                $table->dropForeign(['plant_id']);
            } catch (\Exception $e) {}
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            $table->foreignId('plant_id')->nullable()->change();
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            $table->foreign('plant_id')->references('id')->on('mm_plants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_leave_types', function (Blueprint $table) {
            try {
                $table->dropForeign(['plant_id']);
            } catch (\Exception $e) {}
        });

        Schema::table('mm_leave_types', function (Blueprint $table) {
            $table->foreignId('plant_id')->nullable(false)->change();
        });

        Schema::table('mm_leave_types', function (Blueprint $table) {
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            try {
                $table->dropForeign(['plant_id']);
            } catch (\Exception $e) {}
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            $table->foreignId('plant_id')->nullable(false)->change();
        });

        Schema::table('mm_shifts', function (Blueprint $table) {
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
        });
    }
};
