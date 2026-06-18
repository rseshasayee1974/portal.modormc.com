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
        Schema::create('mm_public_document_links', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50); // 'invoice' or 'report'
            $table->unsignedBigInteger('document_id')->nullable(); // nullable for reports
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('mm_users')->onDelete('set null');
            $table->foreignId('plant_id')->constrained('mm_plants')->onDelete('cascade');
            $table->json('document_params')->nullable(); // to store report filters
            $table->timestamps();

            $table->index(['token']);
            $table->index(['document_type', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_public_document_links');
    }
};
