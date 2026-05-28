<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('shift_name');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('grace_time')->nullable();
            $table->decimal('working_hours', 5, 2);
            $table->boolean('is_night_shift')->default(false);
            $table->auditColumns();
        });

        Schema::create('mm_employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
            $table->foreignId('shift_id')->constrained('mm_shifts')->onDelete('cascade');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->auditColumns();

            $table->index(['personnel_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_employee_shifts');
        Schema::dropIfExists('mm_shifts');
    }
};
