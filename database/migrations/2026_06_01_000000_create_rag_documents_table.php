<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('source_type');        // 'faq', 'sop', 'product', 'policy', 'email', 'notes'
            $table->string('source_id')->nullable(); // FK to the original record if any
            $table->string('title');
            $table->longText('content');           // Full text chunk
            $table->longText('embedding')->nullable(); // JSON float[] from OpenAI
            $table->string('content_hash', 64)->nullable()->index(); // SHA256 dedup
            $table->unsignedInteger('token_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['entity_id', 'source_type']);
            $table->index(['is_active', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
