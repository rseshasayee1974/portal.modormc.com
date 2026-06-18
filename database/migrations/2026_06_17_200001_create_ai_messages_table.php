<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->string('language', 10)->nullable();             // detected language
            $table->string('provider', 50)->nullable();             // gemini | openai | groq | sarvam
            $table->string('model', 100)->nullable();               // model name used
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->boolean('has_audio')->default(false);           // true if TTS was generated
            $table->string('audio_path')->nullable();               // storage path for audio file
            $table->json('rag_sources')->nullable();                // RAG document IDs used
            $table->json('metadata')->nullable();                   // extra provider info
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
