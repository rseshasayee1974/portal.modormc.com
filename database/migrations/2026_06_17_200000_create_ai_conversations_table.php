<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('plant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();      // null = guest/public
            $table->string('session_token', 64)->unique();                   // anonymous session ID
            $table->string('channel', 30)->default('chatbot');               // chatbot | voice | assistant
            $table->string('language', 10)->default('en');                   // BCP-47 language tag
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->boolean('is_escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->string('status', 20)->default('active');                 // active | closed | escalated
            $table->unsignedInteger('message_count')->default(0);
            $table->text('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
