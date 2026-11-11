<?php

/*
 * Author: Cipher
 * Created: 2026-11-11 12:55:08 Asia/Calcutta (UTC+05:30)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('issue_type', 40);
            $table->string('subject', 160);
            $table->text('message');
            $table->string('app_version', 80)->nullable();
            $table->string('device_details', 160)->nullable();
            $table->text('steps_to_reproduce')->nullable();
            $table->string('status', 30)->default('new');
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
