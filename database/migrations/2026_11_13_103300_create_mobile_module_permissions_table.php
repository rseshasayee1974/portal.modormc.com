<?php
/*
Author: Cipher
Created: 2026-11-13 10:33:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_mobile_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('mm_users')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->string('module', 100);
            $table->json('actions');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'plant_id', 'module'], 'mobile_permission_user_plant_module_unique');
            $table->index(['plant_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_mobile_module_permissions');
    }
};
