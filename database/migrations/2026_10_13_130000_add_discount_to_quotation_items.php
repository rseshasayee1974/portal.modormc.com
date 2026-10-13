<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 13:44:06 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            $table->string('discount_type', 10)->default('amount');
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('mm_quotation_items', fn (Blueprint $table) => $table->dropColumn(['discount_type', 'discount', 'discount_amount']));
    }
};
