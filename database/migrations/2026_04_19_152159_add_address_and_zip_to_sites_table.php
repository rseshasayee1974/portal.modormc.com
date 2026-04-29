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
        Schema::table('mm_sites', function (Blueprint $table) {
            $table->string('site_address_1')->nullable()->after('name');
            $table->string('zipcode', 20)->nullable()->after('site_address_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sites', function (Blueprint $table) {
            $table->dropColumn(['site_address_1', 'zipcode']);
        });
    }
};
