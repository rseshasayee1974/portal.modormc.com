<?php
/*
Author: Cipher
Created: 2026-11-06 12:11:16 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changePrecision(36, 12);
    }

    public function down(): void
    {
        $this->changePrecision(18, 2);
    }

    private function changePrecision(int $precision, int $scale): void
    {
        foreach (['mm_stock_exhaust', 'mm_stock_exhaust_lines'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($precision, $scale) {
                foreach (['discount_amount', 'untaxed_amount', 'tax_amount', 'total_amount'] as $column) {
                    $table->decimal($column, $precision, $scale)->default(0)->change();
                }
            });
        }
    }
};
