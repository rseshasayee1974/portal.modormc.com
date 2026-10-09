<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:27:48 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_crm_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->restrictOnDelete();
            $table->string('lead_number')->nullable();
            $table->date('enquiry_date');
            $table->string('contact_name', 200);
            $table->string('company_name', 200)->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('source', 30)->default('Phone');
            $table->string('status', 30)->default('New');
            $table->string('priority', 15)->default('Medium');
            $table->foreignId('assigned_to')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->string('project_name', 200)->nullable();
            $table->string('requirement', 500)->nullable();
            $table->decimal('estimated_quantity', 14, 3)->nullable();
            $table->string('delivery_location', 500)->nullable();
            $table->decimal('expected_value', 16, 2)->default(0);
            $table->date('expected_purchase_date')->nullable();
            $table->text('remarks')->nullable();
            $table->text('lost_reason')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->restrictOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->auditColumns();
            $table->unique(['plant_id', 'lead_number']);
            $table->index(['plant_id', 'status', 'assigned_to']);
        });
        Schema::create('mm_crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->restrictOnDelete();
            $table->foreignId('lead_id')->constrained('mm_crm_leads')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('subject', 200);
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->auditColumns();
            $table->index(['plant_id', 'completed_at', 'due_at']);
        });
        Schema::create('mm_crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->restrictOnDelete();
            $table->foreignId('lead_id')->unique()->constrained('mm_crm_leads')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('mm_patrons')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('stage', 30)->default('Requirement Received');
            $table->decimal('expected_value', 16, 2)->default(0);
            $table->date('expected_close_date')->nullable();
            $table->text('lost_reason')->nullable();
            $table->foreignId('quotation_id')->nullable()->constrained('mm_quotations')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('mm_sales_orders')->nullOnDelete();
            $table->auditColumns();
        });
        Schema::create('mm_crm_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->restrictOnDelete();
            $table->foreignId('lead_id')->constrained('mm_crm_leads')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->auditColumns();
        });
        \App\Services\InstallCrmModule::run();
    }

    public function down(): void
    {
        \App\Services\InstallCrmModule::remove();
        foreach (['mm_crm_attachments', 'mm_crm_deals', 'mm_crm_activities', 'mm_crm_leads'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
