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
        Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
            $table->foreign(['account_type'])->references(['id'])->on('mm_bank_account_types')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['entity_id'])->references(['id'])->on('mm_entities')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
            $table->dropForeign('mm_entity_bank_accounts_account_type_foreign');
            $table->dropForeign('mm_entity_bank_accounts_created_by_foreign');
            $table->dropForeign('mm_entity_bank_accounts_deleted_by_foreign');
            $table->dropForeign('mm_entity_bank_accounts_entity_id_foreign');
            $table->dropForeign('mm_entity_bank_accounts_updated_by_foreign');
        });
    }
};
