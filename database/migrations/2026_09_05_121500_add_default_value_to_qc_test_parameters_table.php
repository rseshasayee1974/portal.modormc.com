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
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            if (!Schema::hasColumn('qc_test_parameters', 'default_value')) {
                $table->string('default_value', 255)->nullable()->after('formula');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_test_parameters', function (Blueprint $table) {
            if (Schema::hasColumn('qc_test_parameters', 'default_value')) {
                $table->dropColumn('default_value');
            }
        });
    }
};
