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
        $tableName = Schema::hasTable('mm_qc_test_types') ? 'mm_qc_test_types' : 'qc_test_types';
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('category', 50)->default('Concrete')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Schema::hasTable('mm_qc_test_types') ? 'mm_qc_test_types' : 'qc_test_types';
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('category', 50)->default('Concrete')->change();
            });
        }
    }
};
