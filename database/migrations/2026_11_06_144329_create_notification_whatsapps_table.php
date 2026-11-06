<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 14:43:29 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_notification_whatsapps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->index();
            $table->string('type', 40);
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['plant_id', 'type', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('mm_notification_whatsapps'); }
};
