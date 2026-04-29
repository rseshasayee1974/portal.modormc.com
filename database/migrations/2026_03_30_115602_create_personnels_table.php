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
        Schema::create('mm_personnels', function (Blueprint $table) {
            $table->id();
            

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('employee_type')->nullable(); // Permanent, Contract, etc.
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('status')->default('active'); // active, inactive, resigned, etc.
            
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_personnels');
    }
};
