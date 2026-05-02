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
        Schema::table('mm_users', function (Blueprint $table) {
            $table->string('login_location', 255)->nullable()->after('login_status')
                  ->comment('Geographic location at time of last login (City, Region, Country)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_users', function (Blueprint $table) {
            $table->dropColumn('login_location');
        });
    }
};
