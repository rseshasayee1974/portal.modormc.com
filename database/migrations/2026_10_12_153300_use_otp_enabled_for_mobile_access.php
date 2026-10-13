<?php
/*
Author: ragul-onemodo
Created: 2026-10-12 15:33:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('mm_users', 'mobile_access_revision')) {
            Schema::table('mm_users', fn (Blueprint $table) => $table->unsignedInteger('mobile_access_revision')->default(0));
        }
        if (Schema::hasColumn('mm_users', 'mobile_access_enabled')) {
            DB::table('mm_users')->where('mobile_access_enabled', true)->update(['is_otp_enabled' => true]);
            Schema::table('mm_users', fn (Blueprint $table) => $table->dropColumn('mobile_access_enabled'));
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('mm_users', 'mobile_access_enabled')) {
            Schema::table('mm_users', fn (Blueprint $table) => $table->boolean('mobile_access_enabled')->default(false));
        }
        DB::table('mm_users')->update(['mobile_access_enabled' => DB::raw('is_otp_enabled')]);
    }
};
