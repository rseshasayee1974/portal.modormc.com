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
        Schema::create('mm_machine_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('machine_id')->constrained('mm_machines')->cascadeOnDelete();

            $table->string('type'); // insurance, fc, permit, road_tax

            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->decimal('amount', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_machine_documents');
    }
};
