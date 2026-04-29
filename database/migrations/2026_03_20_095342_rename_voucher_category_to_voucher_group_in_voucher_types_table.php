<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mm_voucher_types', function (Blueprint $table) {
            $table->renameColumn('voucher_category', 'voucher_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_voucher_types', function (Blueprint $table) {
            $table->renameColumn('voucher_group', 'voucher_category');
        });
    }
};
