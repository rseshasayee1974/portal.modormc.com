<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rag_document_id')->constrained('rag_documents')->cascadeOnDelete();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedSmallInteger('chunk_index');             // sequential position within document
            $table->mediumText('content');                           // chunk text (~500 tokens)
            $table->longText('embedding')->nullable();               // JSON-encoded float[]
            $table->string('content_hash', 64)->index();
            $table->unsignedInteger('token_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['rag_document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
