<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repair headers left active by the old mass-assignment deletion path.
        // Keep the original voucher number and audit history intact.
        DB::table('mm_payments')->whereNotNull('deleted_at')->orderBy('id')
            ->select(['id', 'plant_id', 'deleted_at', 'deleted_by'])
            ->chunkById(200, function ($payments) {
                foreach ($payments as $payment) {
                    DB::table('mm_journal_entries')
                        ->where('ref_module', 'payment')->where('ref_id', $payment->id)
                        ->where('plant_id', $payment->plant_id)->whereNull('deleted_at')
                        ->update([
                            'deleted_at' => $payment->deleted_at,
                            'deleted_by' => $payment->deleted_by,
                            'is_deleted' => 1,
                        ]);
                }
            });

        Schema::table('mm_journal_entries', function (Blueprint $table) {
            // NULL is excluded from MySQL unique comparisons. Other journal
            // sources retain their existing voucher-number restriction.
            $table->string('active_payment_voucher_number', 50)->nullable()
                ->virtualAs("CASE WHEN ref_module = 'payment' AND deleted_at IS NOT NULL THEN NULL ELSE voucher_number END");
            $table->unique(['plant_id', 'voucher_type', 'active_payment_voucher_number'], 'uk_active_voucher');
        });
        Schema::table('mm_journal_entries', fn (Blueprint $table) => $table->dropUnique('uk_voucher'));
    }

    public function down(): void
    {
        // Fail without dropping the active constraint if reused numbers exist.
        // Do not delete or rename historical vouchers to force a rollback.
        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $table->unique(['plant_id', 'voucher_type', 'voucher_number'], 'uk_voucher');
        });
        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $table->dropUnique('uk_active_voucher');
            $table->dropColumn('active_payment_voucher_number');
        });
    }
};
