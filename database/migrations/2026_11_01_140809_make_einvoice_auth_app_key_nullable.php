<?php
/*
Author: ragul-onemodo
Created: 2026-11-01 14:08:09 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_einvoice_auth', function (Blueprint $table) {
            // Gateway authentication can return a token and SEK without an AppKey.
            $table->string('app_key', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('mm_einvoice_auth')->whereNull('app_key')->update(['app_key' => '']);

        Schema::table('mm_einvoice_auth', function (Blueprint $table) {
            $table->string('app_key', 255)->nullable(false)->change();
        });
    }
};
