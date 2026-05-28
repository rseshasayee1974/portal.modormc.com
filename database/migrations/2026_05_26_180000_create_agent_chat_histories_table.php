<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_chat_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('agent_name');          // e.g. "Accountant", "Onemodo"
            $table->string('agent_class');         // FQCN
            $table->string('session_language', 5)->default('en'); // 'en' or 'ta'
            $table->json('messages');              // Full message array [{role, text, ts}]
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->text('summary')->nullable();   // Optional short summary for quick scan
            $table->timestamps();

            $table->index(['agent_name', 'created_at']);
            $table->index(['user_id', 'agent_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_chat_histories');
    }
};
