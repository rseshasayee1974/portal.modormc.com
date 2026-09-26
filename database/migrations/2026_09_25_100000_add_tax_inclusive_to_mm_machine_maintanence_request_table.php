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
        if (Schema::hasTable('mm_machine_maintanence_request')) {
            Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_machine_maintanence_request', 'tax_inclusive')) {
                    $table->tinyInteger('tax_inclusive')->default(0)->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_machine_maintanence_request')) {
            Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
                if (Schema::hasColumn('mm_machine_maintanence_request', 'tax_inclusive')) {
                    $table->dropColumn('tax_inclusive');
                }
            });
        }
    }
};
