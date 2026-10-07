<?php
/*
Author: ragul-onemodo
Created: 2026-10-07 11:00:23 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->enum('document_type', ['INVOICE', 'BILL'])->nullable()->default(null)->change();
            $table->enum('document_source', ['DISPATCH', 'PURCHASE_STOCKIN', 'MANUAL'])->nullable()->default(null)->change();
        });
        // Include soft-deleted documents without triggering accounting or audit events.
        DB::table('mm_invoices')->update(['document_type' => null, 'document_source' => null]);
    }

    public function down(): void
    {
        // These retired fields remain nullable; their former values are not restored.
    }
};
