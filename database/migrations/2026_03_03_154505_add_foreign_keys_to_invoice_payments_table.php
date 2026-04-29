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
        Schema::table('mm_invoice_payments', function (Blueprint $table) {
            $table->foreign(['created_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['deleted_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['gateway_id'])->references(['id'])->on('mm_payment_gateways')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['invoice_id'])->references(['id'])->on('mm_entity_invoices')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['payment_status_id'])->references(['id'])->on('mm_payment_statuses')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['updated_by'])->references(['id'])->on('mm_users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoice_payments', function (Blueprint $table) {
            $table->dropForeign('mm_invoice_payments_created_by_foreign');
            $table->dropForeign('mm_invoice_payments_deleted_by_foreign');
            $table->dropForeign('mm_invoice_payments_gateway_id_foreign');
            $table->dropForeign('mm_invoice_payments_invoice_id_foreign');
            $table->dropForeign('mm_invoice_payments_payment_status_id_foreign');
            $table->dropForeign('mm_invoice_payments_updated_by_foreign');
        });
    }
};
