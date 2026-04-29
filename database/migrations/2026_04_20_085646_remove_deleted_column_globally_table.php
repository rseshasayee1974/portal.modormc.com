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
        $tables = ['ledgers', 'machine_types', 'quantity', 'terms_condition'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('deleted');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['ledgers', 'machine_types', 'quantity', 'terms_condition'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->tinyInteger('deleted')->default(0);
                });
            }
        }
    }
};
