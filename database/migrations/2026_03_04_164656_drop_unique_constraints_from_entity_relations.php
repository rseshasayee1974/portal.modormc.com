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
            $table->dropUnique('mm_entity_addresses_entity_id_address_type_unique');
        });

        Schema::table('mm_entity_contacts', function (Blueprint $table) {
            $table->dropUnique('mm_entity_contacts_entity_id_contact_type_unique');
        });

        Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
            $table->dropUnique('mm_entity_bank_accounts_entity_id_account_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_entity_addresses', function (Blueprint $table) {
            $table->unique(['entity_id', 'address_type']);
        });

        Schema::table('mm_entity_contacts', function (Blueprint $table) {
            $table->unique(['entity_id', 'contact_type']);
        });

        Schema::table('mm_entity_bank_accounts', function (Blueprint $table) {
            $table->unique(['entity_id', 'account_type']);
        });
    }
};
