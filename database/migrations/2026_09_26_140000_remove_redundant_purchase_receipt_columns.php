<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['purchase_received_quantity', 'purchase_receipt_quantities'] as $column) {
            if (Schema::hasColumn('mm_invoice_items', $column)) {
                Schema::table('mm_invoice_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // Receipt quantities are derived from existing purchase records; no duplicate columns to restore.
    }
};
