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
        Schema::table('mm_entities', function (Blueprint $table) {
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_type'])->references(['id'])->on('mm_entity_types')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entities', function (Blueprint $table) {
            $table->dropForeign('mm_entities_created_by_foreign');
            $table->dropForeign('mm_entities_deleted_by_foreign');
            $table->dropForeign('mm_entities_entity_type_foreign');
            $table->dropForeign('mm_entities_updated_by_foreign');
        });
    }
};
