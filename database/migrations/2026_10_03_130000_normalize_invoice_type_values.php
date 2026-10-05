<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Include deleted documents; preserve notes, numbering, timestamps and accounting records.
        DB::table('mm_invoices')->whereRaw("LOWER(TRIM(invoice_type)) IN ('sales', 'invoice')")->update(['invoice_type' => 'Invoice']);
        DB::table('mm_invoices')->whereRaw("LOWER(TRIM(invoice_type)) IN ('purchase', 'bill')")->update(['invoice_type' => 'Bill']);
    }

    public function down(): void
    {
        // Restore the old commercial categories, not the exact historical capitalization/alias.
        DB::table('mm_invoices')->where('invoice_type', 'Invoice')->update(['invoice_type' => 'sales']);
        DB::table('mm_invoices')->where('invoice_type', 'Bill')->update(['invoice_type' => 'bill']);
    }
};
