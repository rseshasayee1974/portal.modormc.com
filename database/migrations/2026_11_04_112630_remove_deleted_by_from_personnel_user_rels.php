<?php
/*
Author: Cipher
Created: 2026-11-04 16:56:30 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mm_personnel_user_rels', 'deleted_by')) {
            Schema::table('mm_personnel_user_rels', fn (Blueprint $table) => $table->dropColumn('deleted_by'));
        }
    }

    public function down(): void
    {
        // The relationship no longer uses deleted_by; rolling back keeps it absent.
    }
};
