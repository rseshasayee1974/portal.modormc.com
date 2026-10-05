<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            // Nullable so existing credit/debit notes retain their distinct document semantics.
            $table->enum('document_type', ['INVOICE', 'BILL'])->nullable();
            $table->enum('document_source', ['DISPATCH', 'PURCHASE_STOCKIN', 'MANUAL'])->nullable();
            $table->index(['plant_id', 'document_type', 'document_source'], 'invoices_document_classification_index');
        });

        $hasDispatchLinks = Schema::hasTable('mm_dispatch_statuses') && Schema::hasColumn('mm_dispatch_statuses', 'invoice_id');
        $hasPurchaseLinks = Schema::hasTable('mm_purchase_orders') && Schema::hasColumn('mm_purchase_orders', 'billing_id');
        // Query builder deliberately includes soft-deleted records and bypasses model/accounting events.
        DB::table('mm_invoices')->select('id', 'invoice_type', 'invoice_label')->orderBy('id')->chunkById(500, function ($rows) use ($hasDispatchLinks, $hasPurchaseLinks) {
            $ids = $rows->pluck('id');
            $dispatchIds = $hasDispatchLinks ? DB::table('mm_dispatch_statuses')->whereIn('invoice_id', $ids)->pluck('invoice_id')->all() : [];
            $purchaseIds = $hasPurchaseLinks ? DB::table('mm_purchase_orders')->whereIn('billing_id', $ids)->pluck('billing_id')->all() : [];
            foreach ($rows as $row) {
                $type = match (strtolower(trim((string) $row->invoice_type))) {
                    'sales', 'invoice' => 'INVOICE',
                    'purchase', 'bill' => 'BILL',
                    default => null,
                };
                if ($type === null) continue;
                $label = strtolower(trim((string) $row->invoice_label));
                $source = 'MANUAL';
                if ($type === 'INVOICE' && (in_array($label, ['dispatch', 'batching'], true) || in_array($row->id, $dispatchIds))) {
                    $source = 'DISPATCH';
                } elseif ($type === 'BILL' && (in_array($label, ['purchase', 'stockin', 'stock-in', 'stock_in', 'purchase/stockin', 'purchase_stockin'], true) || in_array($row->id, $purchaseIds))) {
                    $source = 'PURCHASE_STOCKIN';
                }
                DB::table('mm_invoices')->where('id', $row->id)->update(['document_type' => $type, 'document_source' => $source]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_document_classification_index');
            $table->dropColumn(['document_type', 'document_source']);
        });
    }
};
