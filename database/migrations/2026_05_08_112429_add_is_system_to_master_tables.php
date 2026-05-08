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
        $tables = [
            'mm_accounts',
            'mm_account_types',
            'mm_ledgers',
            'mm_taxes',
            'mm_account_default_settings',
            'mm_product_categories',
            'mm_products',
            'mm_machine_types',
            'mm_concrete_grades',
            'mm_mix_designs',
            'mm_patrons',
            'mm_sites',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableGroup) {
                $tableGroup->boolean('is_system')->default(false)->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'mm_accounts',
            'mm_account_types',
            'mm_ledgers',
            'mm_taxes',
            'mm_account_default_settings',
            'mm_product_categories',
            'mm_products',
            'mm_machine_types',
            'mm_concrete_grades',
            'mm_mix_designs',
            'mm_patrons',
            'mm_sites',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $tableGroup) {
                $tableGroup->dropColumn('is_system');
            });
        }
    }
};
