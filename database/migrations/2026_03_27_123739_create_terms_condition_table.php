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
        Schema::create('mm_terms_condition', function (Blueprint $table) {
            $table->id();
            $table->string('order_type')->nullable(); // e.g. Purchase, Sale, etc.
            $table->text('terms_condition')->nullable();
            $table->foreignId('entity_id')->constrained('mm_entities')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('mm_users');
            $table->foreignId('updated_by')->nullable()->constrained('mm_users');
            $table->foreignId('deleted_by')->nullable()->constrained('mm_users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_terms_condition');
    }
};
