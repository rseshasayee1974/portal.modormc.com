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
        Schema::create('mm_entity_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('entity_id')->index('mm_entity_users_entity_id_foreign');
            $table->unsignedBigInteger('plant_id')->nullable()->index('mm_entity_users_plant_id_foreign');
            $table->unsignedBigInteger('role_id')->index('mm_entity_users_role_id_foreign');
            $table->unsignedBigInteger('created_by')->nullable()->index('mm_entity_users_created_by_foreign');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mm_entity_users_updated_by_foreign');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mm_entity_users_deleted_by_foreign');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'entity_id', 'plant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_entity_users');
    }
};
