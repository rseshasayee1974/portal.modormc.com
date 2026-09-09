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
                if (!Schema::hasColumn('mm_invoices', 'is_tax_inclusive')) {
                    $table->boolean('is_tax_inclusive')->default(false)->after('invoice_label');
                }
                if (!Schema::hasColumn('mm_invoices', 'tax_inclusive')) {
                    $table->boolean('tax_inclusive')->default(false)->after('is_tax_inclusive');
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
                if (Schema::hasColumn('mm_invoices', 'tax_inclusive')) {
                    $table->dropColumn('tax_inclusive');
                }
                if (Schema::hasColumn('mm_invoices', 'is_tax_inclusive')) {
                    $table->dropColumn('is_tax_inclusive');
                }
            });
        }
    }
};
