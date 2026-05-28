<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
            $table->foreignId('shift_id')->nullable()->constrained('mm_shifts')->nullOnDelete();
            $table->date('attendance_date');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->decimal('worked_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('late_hours', 5, 2)->default(0);

            $table->enum('status', ['present', 'absent', 'half_day', 'leave', 'holiday', 'weekoff', 'on_duty']);
            $table->boolean('is_late')->default(false);
            $table->boolean('is_early_departure')->default(false);
            $table->string('source')->default('manual'); // biometric, mobile, web

            $table->auditColumns();

            $table->unique(['personnel_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_attendances');
    }
};
