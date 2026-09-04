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
        Schema::table('mm_concrete_grades', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_concrete_grades', 'hsn_code')) {
                $table->string('hsn_code', 50)->nullable()->after('concrete_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_concrete_grades', function (Blueprint $table) {
            if (Schema::hasColumn('mm_concrete_grades', 'hsn_code')) {
                $table->dropColumn('hsn_code');
            }
        });
    }
};
