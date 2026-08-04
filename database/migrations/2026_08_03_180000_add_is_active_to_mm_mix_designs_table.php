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
        Schema::table('mm_mix_designs', function (Blueprint $table) {
            $table->tinyInteger('is_active')->default(1)->after('rate_per_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_mix_designs', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
