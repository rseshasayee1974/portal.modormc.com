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
        if (!Schema::hasColumn('mm_dispatches', 'operator_id')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->unsignedBigInteger('operator_id')->nullable()->after('driver_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mm_dispatches', 'operator_id')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $table->dropColumn('operator_id');
            });
        }
    }
};
