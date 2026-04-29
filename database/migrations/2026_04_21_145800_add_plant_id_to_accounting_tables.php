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
        Schema::table('mm_account_types', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_account_types', 'plant_id')) {
                $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
                $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            }
        });

        Schema::table('mm_ledgers', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_ledgers', 'plant_id')) {
                $table->unsignedBigInteger('plant_id')->nullable()->after('entity_id');
                $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_account_types', function (Blueprint $table) {
            $table->dropForeign(['plant_id']);
            $table->dropColumn('plant_id');
        });

        Schema::table('mm_ledgers', function (Blueprint $table) {
            $table->dropForeign(['plant_id']);
            $table->dropColumn('plant_id');
        });
    }
};
