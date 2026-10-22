<?php

/*
Author: Cipher
Created: 2026-10-17 14:44:50 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mm_push_notification_audit_logs')) {
            Schema::create('mm_push_notification_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plant_id')->index();
                // Keep history even if the notification or recipient is permanently removed.
                $table->unsignedBigInteger('push_notification_log_id')->index();
                $table->unsignedBigInteger('recipient_user_id')->nullable()->index();
                $table->string('reference_type');
                $table->unsignedBigInteger('reference_id');
                $table->string('action_type', 30)->index();
                $table->string('status_from', 20)->nullable();
                $table->string('status_to', 20)->nullable();
                $table->unsignedInteger('attempt_number')->default(0);
                $table->string('provider', 50)->nullable();
                $table->string('provider_message_id')->nullable()->index();
                $table->json('provider_response')->nullable();
                $table->json('log_from')->nullable();
                $table->json('log_to')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('remarks')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }
        if (! Schema::hasIndex('mm_push_notification_audit_logs', 'push_audit_reference_idx')) {
            Schema::table('mm_push_notification_audit_logs', fn (Blueprint $table) => $table->index(['reference_type', 'reference_id'], 'push_audit_reference_idx'));
        }
        if (! Schema::hasColumn('mm_push_notification_logs', 'provider_response')) {
            Schema::table('mm_push_notification_logs', fn (Blueprint $table) => $table->json('provider_response')->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_push_notification_audit_logs');
        Schema::table('mm_push_notification_logs', fn (Blueprint $table) => $table->dropColumn('provider_response'));
    }
};
