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
        Schema::table('mm_entity_taxes', function (Blueprint $table) {
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
        Schema::table('mm_entity_taxes', function (Blueprint $table) {
            $table->dropForeign('mm_entity_taxes_country_id_foreign');
            $table->dropForeign('mm_entity_taxes_created_by_foreign');
            $table->dropForeign('mm_entity_taxes_deleted_by_foreign');
            $table->dropForeign('mm_entity_taxes_entity_id_foreign');
            $table->dropForeign('mm_entity_taxes_state_id_foreign');
            $table->dropForeign('mm_entity_taxes_updated_by_foreign');
        });
    }
};
