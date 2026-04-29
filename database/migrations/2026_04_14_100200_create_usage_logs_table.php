<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_api_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('mm_users')->cascadeOnDelete();
            $table->string('mm_module', 40);
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->string('endpoint', 120);
            $table->timestamps();

            $table->index(['user_id', 'mm_module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_usage_logs');
    }
};
