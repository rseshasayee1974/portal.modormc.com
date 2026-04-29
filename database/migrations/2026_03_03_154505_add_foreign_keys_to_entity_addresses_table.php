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
        Schema::table('mm_entity_addresses', function (Blueprint $table) {
            $table->foreign(['address_type'])->references(['id'])->on('mm_address_types')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['country_id'])->references(['id'])->on('mm_countries')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'])->references(['id'])->on('mm_entities')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['state_id'])->references(['id'])->on('mm_state_codes')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_addresses', function (Blueprint $table) {
            $table->dropForeign('mm_entity_addresses_address_type_foreign');
            $table->dropForeign('mm_entity_addresses_country_id_foreign');
            $table->dropForeign('mm_entity_addresses_created_by_foreign');
            $table->dropForeign('mm_entity_addresses_deleted_by_foreign');
            $table->dropForeign('mm_entity_addresses_entity_id_foreign');
            $table->dropForeign('mm_entity_addresses_state_id_foreign');
            $table->dropForeign('mm_entity_addresses_updated_by_foreign');
        });
    }
};
