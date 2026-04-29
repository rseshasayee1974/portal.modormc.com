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
        Schema::table('mm_entity_contacts', function (Blueprint $table) {
            $table->string('alt_email', 191)->nullable()->after('mobile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_contacts', function (Blueprint $table) {
            $table->dropColumn('alt_email');
        });
    }
};
