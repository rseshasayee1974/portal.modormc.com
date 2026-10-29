<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:03:34 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mm_patrons', 'aadhar_number')) {
            Schema::table('mm_patrons', function (Blueprint $table) {
                $table->string('aadhar_number', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->dropColumn('aadhar_number');
        });
    }
};
