<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mm_customer_pos')) {
            Schema::table('mm_customer_pos', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_customer_pos', 'amount_untaxed')) {
                    $table->decimal('amount_untaxed', 15, 2)->default(0)->after('is_tax_inclusive');
                }
                if (!Schema::hasColumn('mm_customer_pos', 'amount_tax')) {
                    $table->decimal('amount_tax', 15, 2)->default(0)->after('amount_untaxed');
                }
                if (!Schema::hasColumn('mm_customer_pos', 'amount_total')) {
                    $table->decimal('amount_total', 15, 2)->default(0)->after('amount_tax');
                }
            });

            // Backfill existing Customer PO records
            $customerPOs = DB::table('mm_customer_pos')->get();
            foreach ($customerPOs as $po) {
                $untaxed = DB::table('mm_customer_po_items')
                    ->where('customer_po_id', $po->id)
                    ->whereNull('deleted_at')
                    ->sum('untaxed_amount') ?: 0;

                $tax = DB::table('mm_customer_po_items')
                    ->where('customer_po_id', $po->id)
                    ->whereNull('deleted_at')
                    ->sum('tax_amount') ?: 0;

                $total = DB::table('mm_customer_po_items')
                    ->where('customer_po_id', $po->id)
                    ->whereNull('deleted_at')
                    ->sum('amount_total') ?: 0;

                // Fallback to quotation if no CPO items exist
                if ($total == 0 && $po->quotation_id) {
                    $untaxed = DB::table('mm_quotation_items')
                        ->where('quotation_id', $po->quotation_id)
                        ->whereNull('deleted_at')
                        ->sum('untaxed_amount') ?: 0;

                    $tax = DB::table('mm_quotation_items')
                        ->where('quotation_id', $po->quotation_id)
                        ->whereNull('deleted_at')
                        ->sum('tax_amount') ?: 0;

                    $total = DB::table('mm_quotation_items')
                        ->where('quotation_id', $po->quotation_id)
                        ->whereNull('deleted_at')
                        ->sum('amount_total') ?: 0;
                }

                DB::table('mm_customer_pos')
                    ->where('id', $po->id)
                    ->update([
                        'amount_untaxed' => round((float)$untaxed, 2),
                        'amount_tax' => round((float)$tax, 2),
                        'amount_total' => round((float)$total, 2),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_customer_pos')) {
            Schema::table('mm_customer_pos', function (Blueprint $table) {
                if (Schema::hasColumn('mm_customer_pos', 'amount_total')) {
                    $table->dropColumn('amount_total');
                }
                if (Schema::hasColumn('mm_customer_pos', 'amount_tax')) {
                    $table->dropColumn('amount_tax');
                }
                if (Schema::hasColumn('mm_customer_pos', 'amount_untaxed')) {
                    $table->dropColumn('amount_untaxed');
                }
            });
        }
    }
};
