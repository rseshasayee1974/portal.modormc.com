<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('name'); // Basic, HRA, DA, PF, ESI, Professional Tax...
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('calculation_type', ['₹', '%', 'formula', 'attendance_based']);
            $table->decimal('default_value', 12, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_statutory')->default(false); // PF, ESI, PT, etc.
            $table->json('config')->nullable(); // formula, limits
            $table->auditColumns();
        });

        Schema::create('mm_employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
            $table->foreignId('salary_component_id')->constrained('mm_salary_components')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->auditColumns();

            $table->index(['personnel_id', 'effective_from'], 'emp_sal_struct_eff_index');
        });

        Schema::create('mm_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->json('old_structure');
            $table->json('new_structure');
            $table->text('reason');
            $table->date('revision_date');
            $table->auditColumns();
        });

        Schema::create('mm_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('name'); // May-2026
            $table->date('from_date');
            $table->date('to_date');
            $table->enum('status', ['draft', 'processing', 'completed', 'locked', 'failed']);
            $table->auditColumns();
        });

        Schema::create('mm_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->constrained('mm_payroll_periods')->onDelete('cascade');
            $table->foreignId('personnel_id')->constrained('mm_personnels')->onDelete('cascade');
            $table->string('payslip_no')->unique();
            $table->integer('working_days');
            $table->integer('present_days');
            $table->integer('absent_days');
            $table->integer('paid_leave_days')->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('total_earnings', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_salary', 12, 2);
            $table->enum('status', ['draft', 'approved', 'paid', 'rejected']);
            $table->string('payment_reference')->nullable(); // bank transaction id
            $table->auditColumns();
        });

        Schema::create('mm_payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('mm_payslips')->onDelete('cascade');
            $table->foreignId('salary_component_id')->nullable()->constrained('mm_salary_components')->nullOnDelete();
            $table->string('component_name');
            $table->enum('type', ['earning', 'deduction']);
            $table->decimal('amount', 12, 2);
            $table->string('calculation_source')->nullable(); // fixed, overtime, attendance, loan
            $table->auditColumns();
        });

        Schema::create('mm_statutory_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->string('statute_name'); // PF, ESI, Professional Tax
            $table->json('rules'); // employer rate, employee rate, limits, etc.
            $table->date('effective_from');
            $table->auditColumns();
        });

        Schema::create('mm_hrms_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('personnel_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->string('module');
            $table->string('action');
            $table->unsignedBigInteger('record_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_hrms_audit_logs');
        Schema::dropIfExists('mm_statutory_configs');
        Schema::dropIfExists('mm_payslip_items');
        Schema::dropIfExists('mm_payslips');
        Schema::dropIfExists('mm_payroll_periods');
        Schema::dropIfExists('mm_salary_revisions');
        Schema::dropIfExists('mm_employee_salary_structures');
        Schema::dropIfExists('mm_salary_components');
    }
};
