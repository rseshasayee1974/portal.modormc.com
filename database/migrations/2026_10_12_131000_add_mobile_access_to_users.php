<?php
/*
Author: ragul-onemodo
Created: 2026-10-12 13:09:32 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_users', function(Blueprint $table) {
            $table->boolean('mobile_access_enabled')->default(false);
            $table->unsignedInteger('mobile_access_revision')->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('mm_users',fn(Blueprint $table)=>$table->dropColumn(['mobile_access_enabled','mobile_access_revision']));
    }
};