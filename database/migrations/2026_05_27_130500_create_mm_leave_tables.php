<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_leave_types')) {
            Schema::create('mm_leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->nullOnDelete();
                $table->string('name'); // CL, SL, PL, Maternity, etc.
                $table->boolean('is_paid')->default(true);
                $table->integer('max_days_per_year')->nullable();
                $table->boolean('carry_forward')->default(false);
                $table->auditColumns();
            });
        }

        if (!Schema::hasTable('mm_employee_leave_balances')) {
            Schema::create('mm_employee_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
                $table->foreignId('leave_type_id')->constrained('mm_leave_types')->onDelete('cascade');
                $table->integer('year');
                $table->decimal('opening_balance', 6, 2)->default(0);
                $table->decimal('accrued', 6, 2)->default(0);
                $table->decimal('used', 6, 2)->default(0);
                $table->decimal('balance', 6, 2)->default(0);
                $table->auditColumns();

                $table->unique(['personnel_id', 'leave_type_id', 'year'], 'emp_leave_yr_unique');
            });
        }

        if (!Schema::hasTable('mm_leave_applications')) {
            Schema::create('mm_leave_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
                $table->foreignId('leave_type_id')->constrained('mm_leave_types')->onDelete('cascade');
                $table->date('from_date');
                $table->date('to_date');
                $table->decimal('days', 5, 2);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('mm_users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->auditColumns();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_leave_applications');
        Schema::dropIfExists('mm_employee_leave_balances');
        Schema::dropIfExists('mm_leave_types');
    }
};
