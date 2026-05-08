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
        Schema::table('mm_plants', function (Blueprint $table) {
            $table->string('email_address')->nullable()->after('name');
            $table->string('mobile_number')->nullable()->after('email_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_plants', function (Blueprint $table) {
            $table->dropColumn(['email_address', 'mobile_number']);
        });
    }
};
