<?php
/*
Author: Cipher
Created: 2026-11-13 10:48:11 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_mobile_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module', 100)->index();
            $table->string('action', 50);
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_mobile_permissions');
    }
};
