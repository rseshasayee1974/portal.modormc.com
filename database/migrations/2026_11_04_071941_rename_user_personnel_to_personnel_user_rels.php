<?php
/*
Author: Cipher
Created: 2026-11-04 12:49:41 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('mm_user_personnel', 'mm_personnel_user_rels');
    }

    public function down(): void
    {
        Schema::rename('mm_personnel_user_rels', 'mm_user_personnel');
    }
};
