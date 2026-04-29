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
        Schema::create('mm_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('patron_id')->nullable()->constrained('mm_patrons')->nullOnDelete();

            $table->foreignId('contact_type_id')
                  ->constrained('mm_contact_types')
                  ->restrictOnDelete();

            // Person info
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 45)->nullable();
            $table->string('alt_mobile', 45)->nullable();
            $table->string('landline', 45)->nullable();

            // Flags
            $table->boolean('is_primary')->default(0);
            $table->boolean('status')->default(1);
            $table->boolean('displayed')->default(1);

            $table->timestamps();
            $table->softDeletes();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('mm_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('mm_users')->nullOnDelete();

            // Index
            $table->index(['plant_id', 'patron_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_contacts');
    }
};
