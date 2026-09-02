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
        if (Schema::hasTable('mm_invoices')) {
            Schema::table('mm_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_invoices', 'notes')) {
                    $table->text('notes')->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_invoices')) {
            Schema::table('mm_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('mm_invoices', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
