<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 18:14:02 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_quotation_items', fn (Blueprint $table) => $table->string('discount_type', 10)->nullable()->default(null)->change());
        DB::table('mm_quotation_items')->where('discount', 0)->update(['discount_type' => null]);
    }
    public function down(): void
    {
        DB::table('mm_quotation_items')->whereNull('discount_type')->update(['discount_type' => 'amount']);
        Schema::table('mm_quotation_items', fn (Blueprint $table) => $table->string('discount_type', 10)->nullable(false)->default('amount')->change());
    }
};
