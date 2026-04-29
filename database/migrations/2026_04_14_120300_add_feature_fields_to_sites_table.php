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
            $table->boolean('is_reset')->default(false)->after('is_restricted');
            $table->string('status', 50)->nullable()->after('is_reset');
            $table->boolean('is_active')->default(true)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_sites', function (Blueprint $table) {
            $table->dropColumn(['is_reset', 'status', 'is_active']);
        });
    }
};
