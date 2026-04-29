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
        Schema::create('mm_personnel_contacts', function (Blueprint $table) {
            $table->string('contact_id')->primary(); // PK as requested
            $table->foreignId('employee_id')->constrained('mm_personnels')->cascadeOnDelete();
            
            $table->string('contact_type')->nullable(); // phone, email, emergency_email, etc.
            $table->string('contact_value')->nullable();
            
            $table->boolean('is_primary')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_personnel_contacts');
    }
};
