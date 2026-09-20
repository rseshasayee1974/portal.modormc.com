<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_user_presence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('session_hash', 64);
            $table->string('tab_id', 36);
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('closed')->default(false);
            // Epoch seconds stay independent of entity timezones.
            $table->unsignedBigInteger('expires_at')->index();
            $table->unique(['user_id', 'session_hash', 'tab_id'], 'user_presence_tab_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_user_presence');
    }
};
