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
            $table->index(['plant_id', 'dispatch_time'], 'idx_dispatches_plant_time');
        });

        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->index(['plant_id', 'received_date'], 'idx_po_history_plant_date');
        });

        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $table->index(['plant_id', 'voucher_type', 'voucher_date'], 'idx_journal_entries_plant_type_date');
        });

        Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
            $table->index(['partner_id'], 'idx_journal_lines_partner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->dropIndex('idx_dispatches_plant_time');
        });

        Schema::table('mm_purchase_order_history', function (Blueprint $table) {
            $table->dropIndex('idx_po_history_plant_date');
        });

        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_journal_entries_plant_type_date');
        });

        Schema::table('mm_journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('idx_journal_lines_partner');
        });
    }
};
