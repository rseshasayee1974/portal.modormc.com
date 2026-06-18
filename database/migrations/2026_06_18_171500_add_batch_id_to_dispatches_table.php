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
        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_dispatches', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')->nullable()->index()->after('work_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (Schema::hasColumn('mm_dispatches', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
    }
};
