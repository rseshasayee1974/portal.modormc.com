<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 12:35:13 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mm_purchase_order_history', 'mm_customer_pos'] as $table) {
            Schema::table($table, fn (Blueprint $schema) => $schema->json('attachments')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['mm_purchase_order_history', 'mm_customer_pos'] as $table) {
            Schema::table($table, fn (Blueprint $schema) => $schema->dropColumn('attachments'));
        }
    }
};
