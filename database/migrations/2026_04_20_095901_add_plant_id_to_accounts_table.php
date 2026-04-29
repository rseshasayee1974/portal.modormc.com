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
        Schema::table('mm_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_accounts', 'plant_id')) {
                $table->unsignedBigInteger('plant_id')->nullable()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_accounts', function (Blueprint $table) {
            $table->dropColumn('plant_id');
        });
    }
};
