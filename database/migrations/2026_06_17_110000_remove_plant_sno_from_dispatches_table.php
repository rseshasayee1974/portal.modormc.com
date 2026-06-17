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
        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('mm_dispatches', 'plant_sno')) {
                $table->dropColumn('plant_sno');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->string('plant_sno')->nullable();
        });
    }
};
