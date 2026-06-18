<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->enum('type', ['stt', 'tts']);                    // speech-to-text or text-to-speech
            $table->string('provider', 50)->default('sarvam');
            $table->string('language', 10)->nullable();
            $table->string('input_audio_path')->nullable();          // uploaded audio file
            $table->longText('transcript')->nullable();              // STT result
            $table->longText('input_text')->nullable();              // TTS input
            $table->string('output_audio_path')->nullable();         // TTS result
            $table->unsignedInteger('duration_ms')->nullable();      // audio duration
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_logs');
    }
};
