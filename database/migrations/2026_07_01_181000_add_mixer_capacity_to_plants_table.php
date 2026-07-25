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
        if (!Schema::hasColumn('mm_plants', 'mixer_capacity')) {
            Schema::table('mm_plants', function (Blueprint $table) {
                $table->decimal('mixer_capacity', 8, 2)->nullable()->after('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mm_plants', 'mixer_capacity')) {
            Schema::table('mm_plants', function (Blueprint $table) {
                $table->dropColumn('mixer_capacity');
            });
        }
    }
};
