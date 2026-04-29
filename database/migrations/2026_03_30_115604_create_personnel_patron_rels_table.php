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
        Schema::create('mm_personnel_patron_rels', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('employee_id')->constrained('mm_personnels')->cascadeOnDelete();
            $table->foreignId('patron_id')->constrained('mm_patrons')->cascadeOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_personnel_patron_rels');
    }
};
