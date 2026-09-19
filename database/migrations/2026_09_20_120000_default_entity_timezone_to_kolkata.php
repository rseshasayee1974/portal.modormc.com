<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_entities', function (Blueprint $table) {
            $table->string('time_zone', 50)->nullable()->default('Asia/Kolkata')->change();
        });

        DB::table('mm_entities')->where(function ($query) {
            $query->whereNull('time_zone')->orWhereRaw("TRIM(time_zone) = ''");
        })->update(['time_zone' => 'Asia/Kolkata']);
    }

    public function down(): void
    {
        Schema::table('mm_entities', function (Blueprint $table) {
            $table->string('time_zone', 50)->nullable()->default(null)->change();
        });
    }
};
