<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 10:11:19 Asia/Calcutta (UTC+05:30)
*/
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('mm_stock_exhaust', function (Blueprint $table) {
            $table->boolean('is_tax_inclusive')->default(false);
            foreach (['discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount'] as $column) $table->decimal($column, 18, 2)->default(0);
        });
        Schema::table('mm_stock_exhaust_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->string('discount_type', 10)->default('amount');
            $table->decimal('discount', 18, 2)->default(0);
            foreach (['discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount'] as $column) $table->decimal($column, 18, 2)->default(0);
        });
    }
    public function down(): void {
        Schema::table('mm_stock_exhaust_lines', fn (Blueprint $table) => $table->dropColumn(['tax_id', 'tax_rate', 'unit_price', 'discount_type', 'discount', 'discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount']));
        Schema::table('mm_stock_exhaust', fn (Blueprint $table) => $table->dropColumn(['is_tax_inclusive', 'discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount']));
    }
};
