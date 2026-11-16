<?php
/*
Author: ragul-onemodo
Created: 2026-11-16 13:20:35 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_gps_devices', function (Blueprint $table) {
            $table->string('gpstrack_email')->nullable();
            $table->text('gpstrack_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mm_gps_devices', function (Blueprint $table) {
            $table->dropColumn(['gpstrack_email', 'gpstrack_token']);
        });
    }
};
