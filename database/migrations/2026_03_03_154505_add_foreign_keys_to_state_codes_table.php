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
        Schema::table('mm_state_codes', function (Blueprint $table) {
            $table->foreign(['country_id'])->references(['id'])->on('mm_countries')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_state_codes', function (Blueprint $table) {
            $table->dropForeign('mm_state_codes_country_id_foreign');
        });
    }
};
