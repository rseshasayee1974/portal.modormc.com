<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mm_journal_entries') && !Schema::hasColumn('mm_journal_entries', 'narration_label')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                $table->string('narration_label', 100)->nullable()->after('narration');
            });

            // Backfill existing journal entries with their originating module label
            DB::table('mm_journal_entries')
                ->whereNull('narration_label')
                ->orderBy('id')
                ->chunkById(200, function ($entries) {
                    foreach ($entries as $entry) {
                        $module = strtolower($entry->ref_module ?? '');
                        $vType = strtoupper($entry->voucher_type ?? '');

                        $label = match ($module) {
                            'invoice', 'sales'           => 'Sales',
                            'purchase', 'purchase_order' => 'Purchase',
                            'bill'                       => 'Purchase Bill',
                            'payment'                    => $vType === 'RECEIPT' ? 'Receipt' : 'Payment',
                            'expense'                    => 'Expense',
                            'dispatch'                   => 'Dispatch',
                            'stockin'                    => 'StockIn',
                            'stockout'                   => 'StockOut',
                            'bank_reconciliation', 'brs' => 'Bank Reconciliation',
                            default                      => match ($vType) {
                                'SALES'         => 'Sales',
                                'PURCHASE'      => 'Purchase',
                                'PAYMENT'       => 'Payment',
                                'RECEIPT'       => 'Receipt',
                                'JOURNAL', 'JV' => 'Manual JV',
                                default         => !empty($entry->ref_module) ? ucfirst($entry->ref_module) : 'Manual JV',
                            },
                        };

                        DB::table('mm_journal_entries')
                            ->where('id', $entry->id)
                            ->update(['narration_label' => $label]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_journal_entries') && Schema::hasColumn('mm_journal_entries', 'narration_label')) {
            Schema::table('mm_journal_entries', function (Blueprint $table) {
                $table->dropColumn('narration_label');
            });
        }
    }
};
