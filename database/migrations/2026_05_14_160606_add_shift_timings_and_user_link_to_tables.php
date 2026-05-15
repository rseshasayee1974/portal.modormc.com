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
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('batch_no')->constrained('mm_personnels')->nullOnDelete();
            $table->string('shift')->nullable()->after('operator_id');
        });

        Schema::table('mm_plants', function (Blueprint $table) {
            $table->time('shift_start_time')->default('12:00:00')->after('longitude');
            $table->time('shift_end_time')->default('12:00:00')->after('shift_start_time');
        });

        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('plant_id')->constrained('mm_users')->nullOnDelete();
            $table->time('shift_start_time')->nullable()->after('status');
            $table->time('shift_end_time')->nullable()->after('shift_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_personnels', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'shift_start_time', 'shift_end_time']);
        });

        Schema::table('mm_plants', function (Blueprint $table) {
            $table->dropColumn(['shift_start_time', 'shift_end_time']);
        });

        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn(['operator_id', 'shift']);
        });
    }
};
