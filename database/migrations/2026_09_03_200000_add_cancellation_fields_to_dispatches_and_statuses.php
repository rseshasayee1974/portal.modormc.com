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
        if (Schema::hasTable('mm_dispatches')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatches', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('dispatch_status');
                }
                if (!Schema::hasColumn('mm_dispatches', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
                }
                if (!Schema::hasColumn('mm_dispatches', 'cancelled_notes')) {
                    $table->text('cancelled_notes')->nullable()->after('cancelled_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_dispatches')) {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('mm_dispatches', 'cancelled_at')) $columns[] = 'cancelled_at';
                if (Schema::hasColumn('mm_dispatches', 'cancelled_by')) $columns[] = 'cancelled_by';
                if (Schema::hasColumn('mm_dispatches', 'cancelled_notes')) $columns[] = 'cancelled_notes';
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
