<?php

/*
Author: Cipher
Created: 2026-10-17 14:39:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'mm_push_notification_logs';
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->foreignId($column)->nullable()->constrained('mm_users')->nullOnDelete());
            }
        }
        if (! Schema::hasColumn($tableName, 'deleted_at')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->softDeletes());
        }
        foreach (['last_attempt_at', 'next_attempt_at', 'delivered_at', 'read_at'] as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $field = $table->timestamp($column)->nullable();
                    if ($column === 'next_attempt_at') {
                        $field->index();
                    }
                });
            }
        }
        if (! Schema::hasColumn($tableName, 'provider')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('provider', 50)->nullable());
        }
        if (! Schema::hasColumn($tableName, 'provider_message_id')) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('provider_message_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        // Preserve audit/delivery history on rollback. The creation migration owns the table.
    }
};
