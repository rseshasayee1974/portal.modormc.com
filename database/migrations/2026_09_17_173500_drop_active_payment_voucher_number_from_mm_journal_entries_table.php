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
        if (Schema::hasColumn('mm_journal_entries', 'active_payment_voucher_number')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                if (Schema::hasIndex('mm_journal_entries', 'uk_active_voucher')) {
                    $table->dropUnique('uk_active_voucher');
                }
                $table->dropColumn('active_payment_voucher_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('mm_journal_entries', 'active_payment_voucher_number')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                $table->string('active_payment_voucher_number', 50)->nullable()
                    ->virtualAs("CASE WHEN ref_module = 'payment' AND deleted_at IS NOT NULL THEN NULL ELSE voucher_number END");
                $table->unique(['plant_id', 'voucher_type', 'active_payment_voucher_number'], 'uk_active_voucher');
            });
        }
    }
};
