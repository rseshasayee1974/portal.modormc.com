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
        Schema::table('mm_invoices', function (Blueprint $table) {
            // Drop tax_id if exists
            if (Schema::hasColumn('mm_invoices', 'tax_id')) {
                try { $table->dropForeign(['tax_id']); } catch (\Exception $e) {}
                $table->dropColumn('tax_id');
            }

            // New fields safely
            if (!Schema::hasColumn('mm_invoices', 'einvoice_status')) {
                $table->string('einvoice_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('mm_invoices', 'is_duplicate')) {
                $table->boolean('is_duplicate')->default(false)->after('einvoice_status');
            }
            if (!Schema::hasColumn('mm_invoices', 'period')) {
                $table->string('period')->nullable()->after('invoice_date');
            }
            if (!Schema::hasColumn('mm_invoices', 'is_sent')) {
                $table->boolean('is_sent')->default(false)->after('is_duplicate');
            }
            if (!Schema::hasColumn('mm_invoices', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('partner_id');
            }
            if (!Schema::hasColumn('mm_invoices', 'journal_id')) {
                $table->unsignedBigInteger('journal_id')->nullable()->after('account_id');
            }
            if (!Schema::hasColumn('mm_invoices', 'shipping_charges')) {
                $table->decimal('shipping_charges', 15, 2)->default(0)->after('adjustment');
            }
            if (!Schema::hasColumn('mm_invoices', 'shipping_tax_id')) {
                $table->unsignedBigInteger('shipping_tax_id')->nullable()->after('shipping_charges');
            }
            if (!Schema::hasColumn('mm_invoices', 'is_reconciled')) {
                $table->boolean('is_reconciled')->default(false)->after('is_sent');
            }
            if (!Schema::hasColumn('mm_invoices', 'truck_id')) {
                $table->unsignedBigInteger('truck_id')->nullable()->after('ref_id');
            }

            // Foreign keys - try to add them if they don't exist
            try { $table->foreign('account_id')->references('id')->on('mm_accounts'); } catch (\Exception $e) {}
            try { $table->foreign('journal_id')->references('id')->on('mm_journal_entries'); } catch (\Exception $e) {}
            try { $table->foreign('shipping_tax_id')->references('id')->on('mm_taxes'); } catch (\Exception $e) {}
            try { $table->foreign('truck_id')->references('id')->on('mm_machines'); } catch (\Exception $e) {}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['journal_id']);
            $table->dropForeign(['shipping_tax_id']);
            $table->dropForeign(['truck_id']);

            $table->dropColumn([
                'einvoice_status', 'is_duplicate', 'period', 'is_sent', 
                'account_id', 'journal_id', 'shipping_charges', 
                'shipping_tax_id', 'is_reconciled', 'truck_id'
            ]);

            $table->unsignedBigInteger('tax_id')->nullable();
            $table->foreign('tax_id')->references('id')->on('mm_taxes');
        });
    }
};
