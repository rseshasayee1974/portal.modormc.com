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
        Schema::create('mm_addresses', function (Blueprint $table) {
            $table->id();

             $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();

            $table->foreignId('contact_id')->nullable()
                  ->constrained('mm_contacts')->nullOnDelete();

            $table->foreignId('address_type_id')
                  ->constrained('mm_address_types')
                  ->restrictOnDelete();

            // Address fields
            $table->string('line_1', 200)->nullable();
            $table->string('line_2', 200)->nullable();
            $table->string('city', 150)->nullable();

            // GST ready (India)
            $table->foreignId('state_id')->nullable()
                  ->constrained('mm_state_codes')->nullOnDelete();

            $table->string('zipcode', 20)->nullable();
            $table->string('landmark', 191)->nullable();

            // Optional geo
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

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
            $table->index(['plant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_addresses');
    }
};
