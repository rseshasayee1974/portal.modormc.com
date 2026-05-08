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
            $table->unsignedBigInteger('default_entity_id')->nullable()->after('profile_photo_path');
            $table->unsignedBigInteger('default_plant_id')->nullable()->after('default_entity_id');
            
            $table->foreign('default_entity_id')->references('id')->on('mm_entities')->onDelete('set null');
            $table->foreign('default_plant_id')->references('id')->on('mm_plants')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_users', function (Blueprint $table) {
            $table->dropForeign(['default_entity_id']);
            $table->dropForeign(['default_plant_id']);
            $table->dropColumn(['default_entity_id', 'default_plant_id']);
        });
    }
};
