<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:21:49 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_machine_document_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('machine_document_id')->constrained('mm_machine_documents')->cascadeOnDelete();
            $table->string('recipient', 100);
            $table->date('expiry_date');
            $table->string('reminder_key', 32);
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['machine_document_id', 'recipient', 'expiry_date', 'reminder_key'], 'machine_document_alert_delivery_unique');
            $table->index(['plant_id', 'recipient']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_machine_document_alerts');
    }
};
