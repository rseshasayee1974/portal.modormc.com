<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 15:15:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_sales_order_email_deliveries', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('sales_order_id')->constrained('mm_sales_orders')->cascadeOnDelete();
            $table->string('recipient', 100);
            $table->string('status', 12)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['sales_order_id', 'recipient'], 'so_email_order_recipient_unique');
            $table->index(['status', 'available_at', 'id'], 'so_email_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_sales_order_email_deliveries');
    }
};
